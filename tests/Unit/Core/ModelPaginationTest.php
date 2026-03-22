<?php

namespace Tests\Unit\Core;

use Tests\TestCase;

class ModelPaginationTest extends TestCase
{
    public function testPaginateReturnsItemsAndMeta(): void
    {
        $model = new FakePaginateModel();
        $result = $model->paginate(['is_published' => 1], 2, 5, 'created_at', 'asc', ['id', 'created_at']);

        $this->assertCount(2, $result['items']);
        $this->assertSame(2, $result['meta']['page']);
        $this->assertSame(5, $result['meta']['per_page']);
        $this->assertSame(12, $result['meta']['total']);
        $this->assertSame(3, $result['meta']['total_pages']);
        $this->assertTrue($result['meta']['has_next']);
        $this->assertTrue($result['meta']['has_prev']);
    }

    public function testPaginateSanitizesLimitsAndOrdering(): void
    {
        $model = new FakePaginateModel();
        $result = $model->paginate([], -5, 9999, 'DROP TABLE posts', 'nonsense', ['id', 'created_at']);

        $this->assertSame(1, $result['meta']['page']);
        $this->assertSame(100, $result['meta']['per_page']);

        $queries = $model->queries();
        $this->assertStringContainsString('order by id desc', strtolower($queries[1]['sql']));
        $this->assertStringContainsString('limit 100 offset 0', strtolower($queries[1]['sql']));
    }

    public function testWhereIsStatelessAndUsesExplicitOrdering(): void
    {
        $model = new FakePaginateModel();
        $rows = $model->where(['user_id' => 4], [], [], 10, 20, 'created_at', 'asc', ['id', 'created_at']);

        $this->assertIsArray($rows);
        $queries = $model->queries();
        $sql = strtolower(end($queries)['sql']);

        $this->assertStringContainsString('where user_id = :eq_user_id', $sql);
        $this->assertStringContainsString('order by created_at asc', $sql);
        $this->assertStringContainsString('limit 10 offset 20', $sql);
    }

    public function testFirstExecutesSingleQueryAndReturnsSingleRow(): void
    {
        $model = new FakePaginateModel();
        $row = $model->first(['slug' => 'post-a']);

        $this->assertIsObject($row);
        $this->assertSame(11, $row->id);
        $this->assertCount(1, $model->queries());
    }
}

class FakePaginateModel
{
    use \Model\Model;

    protected $table = 'posts';

    private array $capturedQueries = [];

    public function query(string $query, array $data = [])
    {
        $this->capturedQueries[] = ['sql' => $query, 'data' => $data];

        if (str_contains(strtolower($query), 'count(*)'))
        {
            return [(object)['total' => 12]];
        }

        return [
            (object)['id' => 11, 'title' => 'Post A'],
            (object)['id' => 10, 'title' => 'Post B'],
        ];
    }

    public function queries(): array
    {
        return $this->capturedQueries;
    }
}
