<?php

namespace App\Search\AlgoliaSplit;

use GuzzleHttp\Exception\ConnectException;
use Statamic\Search\Algolia\Index as StatamicIndex;
use Statamic\Search\Documents;
use Statamic\Search\Result;
use Statamic\Support\Str;

class Index extends StatamicIndex
{
    protected function insertDocuments(Documents $documents)
    {
        $documents = $documents->map(function ($item, $id) {
            $item['objectID'] = $id;

            return $item;
        })->values();

        if (isset($this->config['split'])) {
            $documents = $documents->flatMap(fn ($item) => $this->splitDocument($item, $this->config['split']));
            $this->cleanupSplitDocuments($documents);
            $this->configureSplitIndex();
        }

        try {
            $this->getIndex()->saveObjects($documents);
        } catch (ConnectException $e) {
            throw new \Exception('Error connecting to Algolia. Check your API credentials.', 0, $e);
        }
    }

    protected function cleanupSplitDocuments(Documents $documents)
    {
        $objectIDs = $documents->pluck('objectID');
        $sourceIDs = $documents->pluck('sourceID')->unique();

        $filter = $sourceIDs
            ->map(fn ($sourceID) => "sourceID:'".$sourceID."'")
            ->join(' OR ');

        try {
            $response = $this->getIndex()->search('', [
                'filters' => $filter,
                'attributesToRetrieve' => ['objectID', 'sourceID'],
                'attributesToHighlight' => null,
                'distinct' => false,
            ]);
            $staleObjectIDs = collect($response['hits'])
                ->pluck('objectID')
                ->reject(fn ($objectID) => $objectIDs->contains($objectID))
                ->values()
                ->all();
            if (count($staleObjectIDs)) {
                $this->getIndex()->delete($staleObjectIDs);
            }
        } catch (ConnectException $e) {
            throw new \Exception('Error connecting to Algolia. Check your API credentials.', 0, $e);
        }
    }

    protected function configureSplitIndex()
    {
        try {
            $settings = $this->getIndex()->getSettings();
            $this->getIndex()->setSettings([
                'distinct' => true,
                'attributeForDistinct' => 'sourceID',
                'attributesForFaceting' => collect($settings['attributesForFaceting'] ?? [])
                    ->push('sourceID')
                    ->unique()
                    ->all(),
            ]);
        } catch (ConnectException $e) {
            throw new \Exception('Error connecting to Algolia. Check your API credentials.', 0, $e);
        }
    }

    protected function splitDocument($item, $field)
    {
        $item = array_merge($item, [
            'objectID' => $item['objectID'].'::chunk-0',
            'sourceID' => $item['objectID'],
        ]);

        $maxSize = 10_000;
        $getSize = fn ($data) => mb_strlen(json_encode($data));

        $totalSize = $getSize($item);
        if ($totalSize <= $maxSize || ! is_string($item[$field] ?? null)) {
            return [$item];
        }

        $content = $item[$field];
        $partial = array_merge($item, [$field => '']);

        $chunkSize = $maxSize - $getSize($partial);

        $i = 0;
        while (mb_strlen($content)) {
            // The JSON encoded string will probably be longer than the unencoded string, resulting in a document
            // that's over the max size. Rather than try to predict the final size just reduce the chunk size by
            // 10 characters until it fits. This loop will probably only need to run during the first chunk, but
            // if a later chunk happens to go over it will be reduced again.
            do {
                $chunk = Str::safeTruncate($content, $chunkSize);
                $item = array_merge($partial, [
                    'objectID' => $partial['sourceID'].'::chunk-'.$i,
                    $field => $chunk,
                ]);
                $chunkSize = $chunkSize - 10;
            } while ($getSize($item) > $maxSize);
            $content = mb_substr($content, mb_strlen($chunk));
            $documents[] = $item;
            $i++;
        }

        return $documents;
    }

    public function searchUsingApi($query, $fields = null)
    {
        $options = $this->config['options'] ?? [];

        if ($fields) {
            $options['restrictSearchableAttributes'] = implode(',', Arr::wrap($fields));
        }

        try {
            $response = $this->getIndex()->search($query, $options);
        } catch (AlgoliaException $e) {
            $this->handleAlgoliaException($e);
        }

        return collect($response['hits'])->map(function ($hit) {
            $hit['reference'] = isset($this->config['split'])
                ? $hit['sourceID']
                : $hit['objectID'];

            return $hit;
        });
    }

    private function handleAlgoliaException($e)
    {
        if (Str::contains($e->getMessage(), "Index {$this->name} does not exist")) {
            throw new IndexNotFoundException("Index [{$this->name}] does not exist.");
        }

        if (preg_match('/attribute (.*) is not in searchableAttributes/', $e->getMessage(), $matches)) {
            throw new \Exception(
                "Field [{$matches[1]}] does not exist in this index's searchableAttributes list."
            );
        }

        throw $e;
    }

    public function extraAugmentedResultData(Result $result)
    {
        return [
            'search_highlights' => $result->getRawResult()['_highlightResult'] ?? null,
            'search_snippets' => $result->getRawResult()['_snippetResult'] ?? null,
        ];
    }
}
