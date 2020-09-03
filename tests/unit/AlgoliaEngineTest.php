<?php

namespace rias\scout\tests;

use Algolia\AlgoliaSearch\SearchClient;
use Codeception\Test\Unit;
use Craft;
use craft\elements\Entry;
use FakeSearchClient;
use rias\scout\engines\AlgoliaEngine;
use rias\scout\IndexSettings;
use rias\scout\Scout;
use rias\scout\ScoutIndex;
use UnitTester;

class AlgoliaEngineTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /** @var ScoutIndex */
    private $scoutIndex;

    /** @var AlgoliaEngine */
    private $engine;

    /** @var FakeSearchClient */
    private $searchClient;

    /** @var Entry */
    private $model;

    /** @var Entry */
    private $splitModel;

    /** @var Entry */
    private $splitModel2;

    protected function _before()
    {
        parent::_before();

        $scout = new Scout('scout');
        $scout->init();

        Craft::$container->setSingleton(SearchClient::class, function () {
            return new FakeSearchClient();
        });

        $scoutIndex = new ScoutIndex('Blog');
        $scoutIndex->elementType = Entry::class;
        $scoutIndex->transformer = function ($entry) {
            if ($entry->title === 'split') {
                return [
                    'title'   => $entry->title,
                    'article' => [
                        'Paragraph 1',
                        'Paragraph 2',
                    ],
                ];
            }

            return [
                'title' => $entry->title,
            ];
        };
        $scoutIndex->splitElementsOn(['article']);

        $this->scoutIndex = $scoutIndex;
        $this->searchClient = Craft::$container->get(SearchClient::class);
        $this->engine = new AlgoliaEngine($this->scoutIndex, $this->searchClient);

        $model = new Entry();
        $model->title = 'Scout is amazing';
        $model->id = 1;
        $model->siteId = 1;
        $this->model = $model;

        $splitModel = new Entry();
        $splitModel->title = 'split';
        $splitModel->id = 2;
        $splitModel->siteId = 1;
        $this->splitModel = $splitModel;

        $splitModel2 = new Entry();
        $splitModel2->title = 'split';
        $splitModel2->id = 3;
        $splitModel2->siteId = 1;
        $this->splitModel2 = $splitModel2;
    }

    /** @test * */
    public function it_can_index_an_element()
    {
        $scoutIndex = $this->scoutIndex;
        $scoutIndex->splitElementsOn([]);
        $this->engine = new AlgoliaEngine($scoutIndex, $this->searchClient);

        $this->engine->update($this->model);

        $this->assertEquals(1, count($this->searchClient->indexedModels));
        $this->assertEquals('Scout is amazing', $this->searchClient->indexedModels[$this->model->id]['title']);
    }

    /** @test * */
    public function it_can_index_a_split_element()
    {
        $this->engine->update($this->splitModel);

        $this->assertEquals(2, count($this->searchClient->indexedModels));
        $this->assertEquals('split', $this->searchClient->indexedModels["{$this->splitModel->id}_0"]['title']);
        $this->assertEquals('split', $this->searchClient->indexedModels["{$this->splitModel->id}_0"]['title']);
        $this->assertEquals($this->splitModel->id, $this->searchClient->indexedModels["{$this->splitModel->id}_0"]['distinctID']);
        $this->assertEquals($this->splitModel->id, $this->searchClient->indexedModels["{$this->splitModel->id}_0"]['distinctID']);
    }

    /** @test * */
    public function it_sets_the_object_id()
    {
        $this->engine->update($this->model);

        $this->assertEquals(1, count($this->searchClient->indexedModels));
        $this->assertEquals($this->model->id, $this->searchClient->indexedModels[$this->model->id]['objectID']);
    }

    /** @test * */
    public function it_wont_index_an_element_if_it_returns_an_empty_array_from_the_transformer()
    {
        $index = $this->scoutIndex;
        $index->transformer = function () {
            return [];
        };

        $engine = Scout::$plugin->getSettings()->getEngine($index);
        $engine->update($this->model);

        $this->assertEquals(0, count($this->searchClient->indexedModels));
    }

    /** @test * */
    public function it_wont_crash_if_you_call_update_with_an_empty_array()
    {
        $this->engine->update([]);

        $this->assertEquals(0, count($this->searchClient->indexedModels));
    }

    /** @test * */
    public function it_can_delete_an_element_from_the_index()
    {
        $scoutIndex = $this->scoutIndex;
        $scoutIndex->splitElementsOn([]);
        $this->engine = new AlgoliaEngine($scoutIndex, $this->searchClient);

        $this->engine->update($this->model);

        $this->assertEquals(1, count($this->searchClient->indexedModels));

        $this->engine->delete($this->model);

        $this->assertEquals(0, count($this->searchClient->indexedModels));
    }

    /** @test * */
    public function it_can_delete_a_split_element()
    {
        $this->engine->update($this->splitModel);

        $this->assertEquals(2, count($this->searchClient->indexedModels));

        $this->engine->delete($this->splitModel);

        $this->assertEquals(0, count($this->searchClient->indexedModels));
    }

    /** @test * */
    public function it_can_delete_multiple_split_elements()
    {
        $this->engine->update($this->splitModel);
        $this->engine->update($this->splitModel2);

        $this->assertEquals(4, count($this->searchClient->indexedModels));

        $this->engine->delete([
            $this->splitModel,
            $this->splitModel2,
        ]);

        $this->assertEquals(0, count($this->searchClient->indexedModels));
    }

    /** @test * */
    public function it_does_nothing_with_empty_array()
    {
        $this->engine->update($this->splitModel);

        $this->assertEquals(2, count($this->searchClient->indexedModels));

        $this->engine->delete([]);

        $this->assertEquals(2, count($this->searchClient->indexedModels));
    }

    /** @test * */
    public function it_can_flush_the_index()
    {
        $this->engine->update($this->model);

        $this->assertEquals(1, count($this->searchClient->indexedModels));

        $this->engine->flush();

        $this->assertEquals(0, count($this->searchClient->indexedModels));
    }

    /** @test * */
    public function it_can_get_the_scout_index()
    {
        $this->assertEquals($this->scoutIndex, $this->engine->scoutIndex);
    }

    /** @test * */
    public function it_can_update_settings()
    {
        $this->engine->updateSettings(IndexSettings::create()->minWordSizefor2Typos(10));

        $this->assertEquals([
            'minWordSizefor2Typos' => 10,
        ], $this->searchClient->settings);
    }

    /** @test * */
    public function it_can_get_settings()
    {
        $this->engine->updateSettings(IndexSettings::create()->minWordSizefor2Typos(10));

        $this->assertEquals([
            'minWordSizefor2Typos' => 10,
        ], $this->engine->getSettings());
    }

    /** @test * */
    public function it_can_get_total_records()
    {
        $this->assertEquals(0, $this->engine->getTotalRecords());
    }

    /** @test * */
    public function it_can_get_proxy_client_methods()
    {
        $this->assertInstanceOf(SearchClient::class, $this->engine->get());
    }
}
