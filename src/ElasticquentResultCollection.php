<?php namespace Elasticquent;

use Elasticquent\ElasticquentPaginator as Paginator;

class ElasticquentResultCollection extends \Illuminate\Database\Eloquent\Collection
{
    protected $took;
    protected $timed_out;
    protected $shards;
    protected $hits;
    protected $aggregations = null;
    protected $map_model;

    /**
     * Create a new map_model containing Elasticsearch results
     *
     * @param $results elasticsearch results
     * @param $map_model
     */
    public function __construct($results, $map_model)
    {
        // Take our result data and map it
        // to some class properties.
        $this->took         = $results['took'];
        $this->timed_out    = $results['timed_out'];
        $this->shards       = $results['_shards'];
        $this->hits         = $results['hits'];
        $this->aggregations = isset($results['aggregations']) ? $results['aggregations'] : array();

        // Save the map_model we performed the search on.
        $this->map_model = $map_model;

        // Now we need to assign our hits to the
        // items in the collection.
        $this->items = $this->hitsToItems($map_model);
    }

    /**
     * Hits To Items
     *
     * @param Eloquent model map_model $map_model
     *
     * @return array
     */
    private function hitsToItems($map_model)
    {
        $items = array();

        foreach ($this->hits['hits'] as $hit) {
            $model = $map_model[$hit['_type']];

            $items[] = $model->newFromHitBuilder($hit);
        }
        unset($this->hits['hits']);

        return $items;
    }

    /**
     * Total Hits
     *
     * @return int
     */
    public function totalHits()
    {
        return $this->hits['total'];
    }

    /**
     * Max Score
     *
     * @return float
     */
    public function maxScore()
    {
        return $this->hits['max_score'];
    }

    /**
     * Get Shards
     *
     * @return array
     */
    public function getShards()
    {
        return $this->shards;
    }

    /**
     * Took
     *
     * @return string
     */
    public function took()
    {
        return $this->took;
    }

    /**
     * Timed Out
     *
     * @return bool
     */
    public function timedOut()
    {
        return (bool) $this->timed_out;
    }

    /**
     * Get Hits
     *
     * Get the raw hits array from
     * Elasticsearch results.
     *
     * @return array
     */
    public function getHits()
    {
        return $this->hits;
    }

    /**
     * Get aggregations
     *
     * Get the raw hits array from
     * Elasticsearch results.
     *
     * @return array
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     * Paginate Collection
     *
     * @param int $pageLimit
     *
     * @return Paginator
     */
    public function paginate($pageLimit = 25)
    {
        $page = Paginator::resolveCurrentPage() ?: 1;
        // $sliced_items = array_slice($this->items, ($page - 1) * $pageLimit, $pageLimit);
        $sliced_items = $this->items;

        return new Paginator($sliced_items, $this->aggregations, $this->hits, $this->totalHits(), $pageLimit, $page, ['path' => Paginator::resolveCurrentPath()]);
    }

    /**
     * Chunk the underlying collection array.
     *
     * @param  int   $size
     * @param  bool  $preserveKeys
     * @return static
     */
    public function chunk($size, $preserveKeys = false)
    {
        $chunks = [];

        foreach (array_chunk($this->items, $size, $preserveKeys) as $chunk) {
            $chunks[] = new static($chunk, $this->instance);
        }

        return new static($chunks, $this->instance);
    }
}
