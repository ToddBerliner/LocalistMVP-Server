<?php
namespace App\Test\TestCase\Controller;

use Cake\TestSuite\TestCase;
use App\Util\Graph;

class GraphTest extends TestCase {

    private $_graph = [
        'A' => ['B', 'F'],
        'B' => ['A', 'D', 'E'],
        'C' => ['F'],
        'D' => ['B', 'E'],
        'E' => ['B', 'D', 'F'],
        'F' => ['A', 'E', 'C']
    ];

    public function testTraverse() {
        $graph = new Graph($this->_graph);
        $output = $graph->traverse('A');
        $this->assertEmpty($output['queue']);
        $this->assertEmpty(array_diff(
            array_keys($this->_graph),
            array_keys($output['visited'])
        ));
        $this->assertEquals([true],
            array_unique(array_values($output['visited'])));
        $this->assertEquals(['A','B','F','D','E','C'], $output['output']);
    }

    public function testTraverseSplQueue() {
        $graph = new Graph($this->_graph);
        $output = $graph->traverse('A');
        $this->assertEmpty($output['queue']);
        $this->assertEmpty(array_diff(
            array_keys($this->_graph),
            array_keys($output['visited'])
        ));
        $this->assertEquals([true],
            array_unique(array_values($output['visited'])));
        $this->assertEquals(['A','B','F','D','E','C'], $output['output']);
    }

    public function testbreadthFirstSearch() {
        $graph = new Graph($this->_graph);

        $this->assertEquals('D->E->F->C',
            $graph->breadthFirstSearch('D', 'C'));
        $this->assertEquals('B->A->F',
            $graph->breadthFirstSearch('B', 'F'));
        $this->assertEquals('A->F->C',
            $graph->breadthFirstSearch('A', 'C'));
        $this->assertEquals('No path from A to G',
            $graph->breadthFirstSearch('A', 'G'));
    }

}