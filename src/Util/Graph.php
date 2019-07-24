<?php
declare(strict_types=1);

namespace App\Util;

use SplQueue;
use SplDoublyLinkedList;

class Graph {

    private $graph;
    private $visited;

    public function __construct(Array $graph) {
        $this->graph = $graph;
    }

    public function traverse(string $origin): array {

        if (! array_key_exists($origin, $this->graph)) {
            return [];
        }

        // make all vertices not visited
        $this->visited = array_fill_keys(
            array_keys($this->graph), false
        );
        // create the queue
        $q = [];
        // create the output
        $output = [];
        // enqueue the origin
        $q[] = $origin;
        // mark the origin visited
        $this->visited[$origin] = true;
        // add the origin to the output
        $output[] = $origin;

        // while the queue is not empty
        while (! empty($q)) {
            // dequeue the current vertext
            $current = array_shift($q);
            // for each adjacent, if not visited
            foreach($this->graph[$current] as $adjacent) {
                if (!$this->visited[$adjacent]) {
                    // enqueue the adjacent
                    $q[] = $adjacent;
                    // mark the adjacent visited
                    $this->visited[$adjacent] = true;
                    // add the adjacent to the output
                    $output[] = $adjacent;
                }
            }
        }
        return [
            'queue' => $q,
            'visited' => $this->visited,
            'output' => $output
        ];
    }

    public function traverseSplQueue(string $origin): array {
        if (!$this->graph[$origin]) {
            return [];
        }
        // set all verticies to not visited
        $this->visited = array_fill_keys(
            array_keys($this->graph), false
        );
        // create the queue
        $q = new SplQueue();
        // create the output
        $output = [];
        // enqueue the origin
        $q->enqueue($origin);
        // mark the origin visited
        $this->visited[$origin] = true;
        // add the origin to the output
        $output[] = $origin;

        // while the queue is not empty
        while (!$q->isEmpty()) {
            // dequeue the current
            $current = $q->dequeue();
            // for each adjacent that's not visited
            foreach($this->graph[$current] as $adjacent) {
                if (!$this->visited[$adjacent]) {
                    // enqueue the adjacent
                    $q->enqueue($adjacent);
                    // mark the adjacent visited
                    $this->visited[$adjacent] = true;
                    // add the adjacent to the output
                    $output[] = $adjacent;
                }
            }
        }

        // return the output
        return [
            'queue' => $q,
            'visited' => $this->visited,
            'output' => $output
        ];
    }

    public function breadthFirstSearch(string $origin,
                                               string $destination): string {
        if (! $this->graph[$origin]) {
            return "Origin not present";
        }

        // mark all verticies not visited
        $this->visited = array_fill_keys(
            array_keys($this->graph), false
        );
        // create the queue
        $q = [];
        // create the paths
        $paths = [];
        // enqueue the origin
        $q[] = $origin;
        // mark the origin as visited
        $this->visited[$origin] = true;
        // set the path for the origin
        $paths[$origin] = [$origin];

        // while the queue is not empty and
        // the bottom is not the destination
        while (!empty($q) && $q[0] != $destination) {
            // dequeue the current
            $current = array_shift($q);
            // for each adjacent that's not visited
            foreach($this->graph[$current] as $adjacent) {
                if (!$this->visited[$adjacent]) {
                    // enqueue the adjacent
                    $q[] = $adjacent;
                    // mark the adjacent visited
                    $this->visited[$adjacent] = true;
                    // create the path to the adjacent
                    $paths[$adjacent] = $paths[$current];
                    $paths[$adjacent][] = $adjacent;
                }
            }
        }

        if (!isset($paths[$destination])) {
            return "No path from $origin to $destination";
        }
        return implode('->', $paths[$destination]);
    }
}