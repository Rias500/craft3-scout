<?php

namespace rias\scout\utilities;

use Craft;
use craft\base\Utility;
use craft\db\Table;
use craft\events\RegisterCacheOptionsEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\FileHelper;
use craft\web\assets\clearcaches\ClearCachesAsset;
use rias\scout\engines\Engine;
use rias\scout\Scout;
use yii\base\Event;
use yii\base\InvalidArgumentException;

class ScoutUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('scout', 'Scout Indices');
    }

    public static function id(): string
    {
        return 'scout-indices';
    }

    public static function iconPath()
    {
        return Craft::getAlias('@app/icons/magnifying-glass.svg');
    }

    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();

        $engines = Scout::$plugin->getSettings()->getEngines();

        $stats = $engines->map(function (Engine $engine) {
            return [
                'name' => $engine->scoutIndex->indexName,
                'elementType' => $engine->scoutIndex->elementType,
                'site' => Craft::$app->getSites()->getSiteById($engine->scoutIndex->criteria->siteId),
                'indexed' => $engine->getTotalRecords(),
                'elements' => $engine->scoutIndex->criteria->count(),
            ];
        });

        return $view->renderTemplate('scout/utility', [
            'stats' => $stats,
        ]);
    }
}