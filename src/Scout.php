<?php

namespace rias\scout;

use Algolia\AlgoliaSearch\Config\SearchConfig;
use Algolia\AlgoliaSearch\SearchClient;
use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use Exception;
use rias\scout\behaviors\SearchableBehavior;
use rias\scout\models\Settings;
use rias\scout\utilities\ScoutUtility;
use rias\scout\variables\ScoutVariable;
use yii\base\Event;

class Scout extends Plugin
{
    const EDITION_STANDARD = 'standard';
    const EDITION_PRO = 'pro';

    public static function editions(): array
    {
        return [
            self::EDITION_STANDARD,
            self::EDITION_PRO,
        ];
    }

    /** @var \rias\scout\Scout */
    public static $plugin;

    public $hasCpSettings = true;

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        Craft::$container->setSingleton(SearchClient::class, function () {
            $config = SearchConfig::create(
                Scout::$plugin->getSettings()->getApplicationId(),
                Scout::$plugin->getSettings()->getAdminApiKey()
            );

            $config->setConnectTimeout($this->getSettings()->connect_timeout);

            return SearchClient::createWithConfig($config);
        });

        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            $this->controllerNamespace = 'rias\scout\console\controllers\scout';
        }

        $this->validateConfig();
        $this->registerBehaviors();
        $this->registerVariables();

        if (self::getInstance()->is(self::EDITION_PRO)) {
            $this->registerUtility();
        }
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    public function getSettings(): Settings
    {
        return parent::getSettings();
    }

    /** @codeCoverageIgnore */
    protected function settingsHtml()
    {
        $overrides = Craft::$app->getConfig()->getConfigFromFile(strtolower($this->handle));

        return Craft::$app->getView()->renderTemplate('scout/settings', [
            'settings' => $this->getSettings(),
            'overrides' => array_keys($overrides),
        ]);
    }

    private function registerUtility(): void
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = ScoutUtility::class;
            }
        );
    }

    private function registerBehaviors(): void
    {
        // Register the behavior on the Element class
        Event::on(
            Element::class,
            Element::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $event) {
                $event->behaviors['searchable'] = SearchableBehavior::class;
            }
        );
    }

    private function registerVariables(): void
    {
        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('scout', ScoutVariable::class);
            }
        );
    }

    private function validateConfig(): void
    {
        $indices = $this->getSettings()->getIndices();

        if ($indices->unique('indexName')->count() !== $indices->count()) {
            throw new Exception("Index names must be unique in the Scout config.");
        }
    }
}
