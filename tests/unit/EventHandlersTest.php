<?php

namespace rias\scout\tests;

use Codeception\Test\Unit;
use Craft;
use craft\elements\Entry;
use craft\fields\Entries;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use FakeEngine;
use rias\scout\Scout;
use rias\scout\ScoutIndex;
use UnitTester;

class EventHandlersTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /** @var \craft\models\Section */
    private $section;

    /** @var \craft\elements\Entry */
    private $element;

    /** @var \craft\elements\Entry */
    private $element2;

    /** @var \rias\scout\Scout */
    private $scout;

    protected function _before()
    {
        parent::_before();

        $section = new Section([
            'name'         => 'News',
            'handle'       => 'news',
            'type'         => Section::TYPE_CHANNEL,
            'siteSettings' => [
                new Section_SiteSettings([
                    'siteId'           => Craft::$app->getSites()->getPrimarySite()->id,
                    'enabledByDefault' => true,
                    'hasUrls'          => true,
                    'uriFormat'        => 'foo/{slug}',
                    'template'         => 'foo/_entry',
                ]),
            ],
        ]);

        Craft::$app->getSections()->saveSection($section);

        $this->section = $section;

        $scoutIndex = new ScoutIndex('Blog');
        $scoutIndex->elementType(Entry::class);
        $scoutIndex->criteria(function ($query) {
            return $query;
        });
        $scoutIndex->transformer = function ($entry) {
            return [
                'title' => $entry->title,
            ];
        };
        $scout = new Scout('scout');
        $scout->setSettings([
            'indices' => [$scoutIndex],
            'engine'  => FakeEngine::class,
            'queue'   => false,
        ]);

        $this->scout = $scout;

        $element = new Entry();
        $element->siteId = 1;
        $element->sectionId = $this->section->id;
        $element->typeId = $this->section->getEntryTypes()[0]->id;
        $element->title = 'A new beginning.';
        $element->slug = 'a-new-beginning';

        Craft::$app->getElements()->saveElement($element);

        $this->element = $element;

        $element2 = new Entry();
        $element2->siteId = 1;
        $element2->sectionId = $this->section->id;
        $element2->typeId = $this->section->getEntryTypes()[0]->id;
        $element2->title = 'Second element.';
        $element2->slug = 'second-element';

        Craft::$app->getElements()->saveElement($element2);

        $this->element2 = $element2;
    }

    /** @test * */
    public function it_attaches_to_the_element_save_event_once()
    {
        Craft::$app->getCache()->set("scout-Blog-{$this->element->id}-updateCalled", 0);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));

        Craft::$app->getElements()->saveElement($this->element);

        $this->assertEquals(1, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));
    }

    /** @test * */
    public function it_also_updates_related_elements()
    {
        $relationField = new Entries([
            'name'   => 'Entry field',
            'handle' => 'entryField',
        ]);
        Craft::$app->getFields()->saveField($relationField);

        Craft::$app->getRelations()->saveRelations($relationField, $this->element, [$this->element2->id]);

        Craft::$app->getCache()->set("scout-Blog-{$this->element->id}-updateCalled", 0);
        Craft::$app->getCache()->set("scout-Blog-{$this->element2->id}-updateCalled", 0);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));
        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element2->id}-updateCalled"));

        Craft::$app->getElements()->saveElement($this->element);

        $this->assertEquals(1, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));
        $this->assertEquals(1, Craft::$app->getCache()->get("scout-Blog-{$this->element2->id}-updateCalled"));
    }

    /** @test * */
    public function it_doesnt_update_related_elements_when_indexRelations_is_false()
    {
        $this->scout->setSettings(['indexRelations' => false]);

        $relationField = new Entries([
            'name'   => 'Entry field',
            'handle' => 'entryField',
        ]);

        Craft::$app->getFields()->saveField($relationField);

        Craft::$app->getRelations()->saveRelations($relationField, $this->element, [$this->element2->id]);

        Craft::$app->getCache()->set("scout-Blog-{$this->element->id}-updateCalled", 0);
        Craft::$app->getCache()->set("scout-Blog-{$this->element2->id}-updateCalled", 0);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));
        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element2->id}-updateCalled"));

        Craft::$app->getElements()->saveElement($this->element);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));
        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element2->id}-updateCalled"));
    }

    /** @test * */
    public function it_doesnt_to_anything_when_sync_is_false()
    {
        $this->scout->setSettings(['sync' => false]);

        Craft::$app->getCache()->set("scout-Blog-{$this->element->id}-updateCalled", 0);
        Craft::$app->getCache()->set("scout-Blog-{$this->element->id}-deleteCalled", 0);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));
        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-deleteCalled"));

        Craft::$app->getElements()->saveElement($this->element);
        Craft::$app->getElements()->deleteElement($this->element);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));
        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-deleteCalled"));
    }

    /** @test * */
    public function it_attaches_to_the_element_move_in_structure()
    {
        Craft::$app->getCache()->set("scout-Blog-{$this->element->id}-updateCalled", 0);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));

        Craft::$app->getElements()->updateElementSlugAndUri($this->element);

        $this->assertEquals(1, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));
    }

    /** @test * */
    public function it_attaches_to_the_element_restore_event()
    {
        Craft::$app->getCache()->set("scout-Blog-{$this->element->id}-updateCalled", 0);
        Craft::$app->getElements()->deleteElement($this->element);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));

        Craft::$app->getElements()->restoreElement($this->element);

        $this->assertEquals(1, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-updateCalled"));
    }

    /** @test * */
    public function it_attaches_to_the_element_after_delete_event()
    {
        Craft::$app->getCache()->set("scout-Blog-{$this->element->id}-deleteCalled", 0);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-deleteCalled"));

        Craft::$app->getElements()->deleteElement($this->element);

        $this->assertEquals(1, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-deleteCalled"));
    }

    /** @test * */
    public function it_also_updates_related_elements_before_delete()
    {
        $relationField = new Entries([
            'name'   => 'Entry field',
            'handle' => 'entryField',
        ]);
        Craft::$app->getFields()->saveField($relationField);

        Craft::$app->getRelations()->saveRelations($relationField, $this->element, [$this->element2->id]);

        Craft::$app->getCache()->set("scout-Blog-{$this->element->id}-deleteCalled", 0);
        Craft::$app->getCache()->set("scout-Blog-{$this->element2->id}-updateCalled", 0);

        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-deleteCalled"));
        $this->assertEquals(0, Craft::$app->getCache()->get("scout-Blog-{$this->element2->id}-updateCalled"));

        Craft::$app->getElements()->deleteElement($this->element);

        $this->assertEquals(1, Craft::$app->getCache()->get("scout-Blog-{$this->element->id}-deleteCalled"));
        $this->assertEquals(1, Craft::$app->getCache()->get("scout-Blog-{$this->element2->id}-updateCalled"));
    }
}
