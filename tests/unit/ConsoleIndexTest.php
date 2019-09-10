<?php

namespace yournamespace\tests;

use Craft;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\test\console\ConsoleTest;
use FakeEngine;
use rias\scout\ScoutIndex;
use UnitTester;
use yii\console\ExitCode;

class ConsoleIndexTest extends ConsoleTest
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /** @var \craft\elements\Entry */
    private $element;

    /** @var \craft\elements\Entry */
    private $element2;

    protected function _before()
    {
        parent::_before();

        Craft::$app->getPlugins()->installPlugin('scout');

        $scout = Craft::$app->getPlugins()->getPlugin('scout');
        $scout->setSettings([
            'engine'  => FakeEngine::class,
            'indices' => [
                ScoutIndex::create('blog_nl')->criteria(function (EntryQuery $query) {
                    return $query->anyStatus()->site('*')->title('NL');
                })->transformer(function (Entry $entry) {
                    return ['title' => $entry->title];
                }),
                ScoutIndex::create('blog_fr')->criteria(function (EntryQuery $query) {
                    return $query->anyStatus()->site('*')->title('FR');
                })->transformer(function (Entry $entry) {
                    return ['title' => $entry->title];
                }),
            ],
        ]);

        $section = new Section([
            'name'         => 'News',
            'handle'       => 'news',
            'type'         => Section::TYPE_CHANNEL,
            'siteSettings' => [
                new Section_SiteSettings([
                    'siteId'           => Craft::$app->getSites()->getPrimarySite()->id,
                    'enabledByDefault' => true,
                    'hasUrls'          => false,
                ]),
            ],
        ]);

        Craft::$app->getSections()->saveSection($section);

        $element = new Entry();
        $element->siteId = 1;
        $element->sectionId = $section->id;
        $element->typeId = $section->getEntryTypes()[0]->id;
        $element->title = 'NL';
        $element->slug = 'nl';

        Craft::$app->getElements()->saveElement($element, false, false);

        $this->element = $element;

        $element2 = new Entry();
        $element2->siteId = 1;
        $element2->sectionId = $section->id;
        $element2->typeId = $section->getEntryTypes()[0]->id;
        $element2->title = 'FR';
        $element2->slug = 'fr';

        Craft::$app->getElements()->saveElement($element2, false, false);

        $this->element2 = $element2;

        Craft::$app->getCache()->flush();
    }

    /** @test * */
    public function it_can_flush_all_indices()
    {
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));

        $this->consoleCommand('scout/index/flush')
            ->confirm('Are you sure you want to flush Scout?', true)
            ->stdOut("Flushed index blog_nl\n")
            ->stdOut("Flushed index blog_fr\n")
            ->exitCode(ExitCode::OK)
            ->run();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
    }

    /** @test * */
    public function it_can_flush_a_specific_index()
    {
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));

        $this->consoleCommand('scout/index/flush', ['blog_nl'])
            ->confirm('Are you sure you want to flush Scout?', true)
            ->stdOut("Flushed index blog_nl\n")
            ->exitCode(ExitCode::OK)
            ->run();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
    }

    /** @test * */
    public function it_needs_confirmation_to_flush()
    {
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));

        $this->consoleCommand('scout/index/flush')
            ->confirm('Are you sure you want to flush Scout?', false)
            ->exitCode(ExitCode::OK)
            ->run();

        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
    }

    /** @test * */
    public function it_can_force_flush()
    {
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));

        $this->consoleCommand('scout/index/flush', ['force' => true])
            ->stdOut("Flushed index blog_nl\n")
            ->stdOut("Flushed index blog_fr\n")
            ->exitCode(ExitCode::OK)
            ->run();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
    }

    /** @test * */
    public function it_can_update_all_indices()
    {
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));

        $this->consoleCommand('scout/index/import')
            ->stdOut("Updated 1/1 element(s) in blog_nl\n")
            ->stdOut("Updated 1/1 element(s) in blog_fr\n")
            ->exitCode(ExitCode::OK)
            ->run();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));
    }

    /** @test * */
    public function it_can_update_a_specific_index()
    {
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));

        $this->consoleCommand('scout/index/import', ['blog_nl'])
            ->stdOut("Updated 1/1 element(s) in blog_nl\n")
            ->exitCode(ExitCode::OK)
            ->run();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));
    }

    /** @test * */
    public function it_can_refresh_all_indices()
    {
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));

        $this->consoleCommand('scout/index/refresh')
            ->confirm('Are you sure you want to flush Scout?', true)
            ->stdOut("Flushed index blog_nl\n")
            ->stdOut("Flushed index blog_fr\n")
            ->stdOut("Updated 1/1 element(s) in blog_nl\n")
            ->stdOut("Updated 1/1 element(s) in blog_fr\n")
            ->exitCode(ExitCode::OK)
            ->run();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));
    }

    /** @test * */
    public function it_can_refresh_a_specific_index()
    {
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));

        $this->consoleCommand('scout/index/refresh', ['blog_nl'])
            ->confirm('Are you sure you want to flush Scout?', true)
            ->stdOut("Flushed index blog_nl\n")
            ->stdOut("Updated 1/1 element(s) in blog_nl\n")
            ->exitCode(ExitCode::OK)
            ->run();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));
    }

    /** @test * */
    public function it_can_force_refresh_indices()
    {
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(0, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));

        $this->consoleCommand('scout/index/refresh', ['force' => true])
            ->stdOut("Flushed index blog_nl\n")
            ->stdOut("Flushed index blog_fr\n")
            ->stdOut("Updated 1/1 element(s) in blog_nl\n")
            ->stdOut("Updated 1/1 element(s) in blog_fr\n")
            ->exitCode(ExitCode::OK)
            ->run();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));
    }
}
