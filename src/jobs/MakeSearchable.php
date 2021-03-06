<?php

namespace rias\scout\jobs;

use Craft;
use craft\base\Element;
use craft\queue\BaseJob;
use rias\scout\engines\Engine;

class MakeSearchable extends BaseJob
{
    /** @var int */
    public $id;

    /** @var int */
    public $siteId;

    /** @var string */
    public $indexName;

    /** @var bool */
    public $propagate = true;

    public function execute($queue)
    {
        if (!$element = $this->getElement()) {
            return;
        }

        $engine = $element->searchableUsing()->first(function (Engine $engine) {
            return $engine->scoutIndex->indexName === $this->indexName;
        });

        if (!$engine) {
            return;
        }

        $engine->update($element);

        if ($this->propagate) {
            $element->searchableRelations();
        }
    }

    protected function defaultDescription()
    {
        if (!$element = $this->getElement()) {
            return '';
        }

        return sprintf(
            'Indexing “%s” in “%s”',
            ($element->title ?? $element->id),
            $this->indexName
        );
    }

    /**
     * We use this method instead of setting a prop in the constructor,
     * because Yii will serialize the entire class into the queue table,
     * including the gigantic element prop.
     *
     * @return Element
     */
    private function getElement()
    {
        return Craft::$app->getElements()->getElementById($this->id, null, $this->siteId);
    }
}
