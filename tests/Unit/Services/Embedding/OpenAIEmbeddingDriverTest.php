<?php

namespace Tests\Unit\Services\Embedding;

use App\Services\Embedding\OpenAIEmbeddingDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIEmbeddingDriverTest extends TestCase
{
    public function test_embed_returns_embedding_array(): void
    {
        $expectedEmbedding = array_fill(0, 1536, 0.1);

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => $expectedEmbedding, 'index' => 0],
                ],
            ], 200),
        ]);

        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
            'model' => 'text-embedding-3-small',
        ]);

        $result = $driver->embed('test text');

        $this->assertEquals($expectedEmbedding, $result);
    }

    public function test_embed_throws_without_api_key(): void
    {
        $driver = new OpenAIEmbeddingDriver([
            'api_key' => '',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API key not configured');

        $driver->embed('test text');
    }

    public function test_embed_throws_on_empty_response(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [], 'index' => 0],
                ],
            ], 200),
        ]);

        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('empty embedding');

        $driver->embed('test text');
    }

    public function test_embed_throws_on_api_error(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
        ]);

        $this->expectException(\RuntimeException::class);

        $driver->embed('test text');
    }

    public function test_embed_batch_processes_multiple_texts(): void
    {
        $embedding1 = array_fill(0, 1536, 0.1);
        $embedding2 = array_fill(0, 1536, 0.2);

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => $embedding1, 'index' => 0],
                    ['embedding' => $embedding2, 'index' => 1],
                ],
            ], 200),
        ]);

        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
        ]);

        $results = $driver->embedBatch(['text 1', 'text 2']);

        $this->assertCount(2, $results);
        $this->assertEquals($embedding1, $results[0]);
        $this->assertEquals($embedding2, $results[1]);
    }

    public function test_embed_batch_handles_out_of_order_response(): void
    {
        $embedding1 = array_fill(0, 1536, 0.1);
        $embedding2 = array_fill(0, 1536, 0.2);

        // OpenAI might return in different order
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => $embedding2, 'index' => 1],
                    ['embedding' => $embedding1, 'index' => 0],
                ],
            ], 200),
        ]);

        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
        ]);

        $results = $driver->embedBatch(['text 1', 'text 2']);

        // Should be reordered by index
        $this->assertEquals($embedding1, $results[0]);
        $this->assertEquals($embedding2, $results[1]);
    }

    public function test_embed_batch_returns_empty_for_empty_input(): void
    {
        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
        ]);

        $results = $driver->embedBatch([]);

        $this->assertEquals([], $results);
    }

    public function test_is_available_returns_true_with_valid_api_key(): void
    {
        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
        ]);

        $this->assertTrue($driver->isAvailable());
    }

    public function test_is_available_returns_false_without_api_key(): void
    {
        $driver = new OpenAIEmbeddingDriver([
            'api_key' => '',
        ]);

        $this->assertFalse($driver->isAvailable());
    }

    public function test_is_available_returns_false_on_api_error(): void
    {
        Http::fake([
            'api.openai.com/v1/models' => Http::response('Unauthorized', 401),
        ]);

        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'invalid-key',
        ]);

        $this->assertFalse($driver->isAvailable());
    }

    public function test_get_model_name_returns_configured_model(): void
    {
        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
            'model' => 'text-embedding-ada-002',
        ]);

        $this->assertEquals('text-embedding-ada-002', $driver->getModelName());
    }

    public function test_get_dimensions_returns_configured_dimensions(): void
    {
        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
            'dimensions' => 3072,
        ]);

        $this->assertEquals(3072, $driver->getDimensions());
    }

    public function test_defaults_are_applied(): void
    {
        $driver = new OpenAIEmbeddingDriver([
            'api_key' => 'test-key',
        ]);

        $this->assertEquals('text-embedding-3-small', $driver->getModelName());
        $this->assertEquals(1536, $driver->getDimensions());
    }
}
