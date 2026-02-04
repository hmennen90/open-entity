<?php

namespace Tests\Unit\Services\Embedding;

use App\Services\Embedding\OllamaEmbeddingDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaEmbeddingDriverTest extends TestCase
{
    public function test_embed_returns_embedding_array(): void
    {
        $expectedEmbedding = array_fill(0, 768, 0.1);

        Http::fake([
            '*/api/embeddings' => Http::response([
                'embedding' => $expectedEmbedding,
            ], 200),
        ]);

        $driver = new OllamaEmbeddingDriver([
            'base_url' => 'http://localhost:11434',
            'model' => 'nomic-embed-text',
        ]);

        $result = $driver->embed('test text');

        $this->assertEquals($expectedEmbedding, $result);
    }

    public function test_embed_throws_on_empty_response(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response([
                'embedding' => [],
            ], 200),
        ]);

        $driver = new OllamaEmbeddingDriver([
            'base_url' => 'http://localhost:11434',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('empty embedding');

        $driver->embed('test text');
    }

    public function test_embed_throws_on_api_error(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response('Internal Server Error', 500),
        ]);

        $driver = new OllamaEmbeddingDriver([
            'base_url' => 'http://localhost:11434',
        ]);

        $this->expectException(\RuntimeException::class);

        $driver->embed('test text');
    }

    public function test_embed_batch_processes_multiple_texts(): void
    {
        $embedding1 = array_fill(0, 768, 0.1);
        $embedding2 = array_fill(0, 768, 0.2);

        Http::fake([
            '*/api/embeddings' => Http::sequence()
                ->push(['embedding' => $embedding1])
                ->push(['embedding' => $embedding2]),
        ]);

        $driver = new OllamaEmbeddingDriver([
            'base_url' => 'http://localhost:11434',
        ]);

        $results = $driver->embedBatch(['text 1', 'text 2']);

        $this->assertCount(2, $results);
        $this->assertEquals($embedding1, $results[0]);
        $this->assertEquals($embedding2, $results[1]);
    }

    public function test_is_available_returns_true_when_service_responds(): void
    {
        Http::fake([
            '*/api/tags' => Http::response([
                'models' => [
                    ['name' => 'nomic-embed-text:latest'],
                ],
            ], 200),
        ]);

        $driver = new OllamaEmbeddingDriver([
            'base_url' => 'http://localhost:11434',
            'model' => 'nomic-embed-text',
        ]);

        $this->assertTrue($driver->isAvailable());
    }

    public function test_is_available_returns_false_on_connection_error(): void
    {
        Http::fake([
            '*/api/tags' => Http::response(null, 500),
        ]);

        $driver = new OllamaEmbeddingDriver([
            'base_url' => 'http://localhost:11434',
        ]);

        $this->assertFalse($driver->isAvailable());
    }

    public function test_get_model_name_returns_configured_model(): void
    {
        $driver = new OllamaEmbeddingDriver([
            'model' => 'custom-embedding-model',
        ]);

        $this->assertEquals('custom-embedding-model', $driver->getModelName());
    }

    public function test_get_dimensions_returns_configured_dimensions(): void
    {
        $driver = new OllamaEmbeddingDriver([
            'dimensions' => 1024,
        ]);

        $this->assertEquals(1024, $driver->getDimensions());
    }

    public function test_defaults_are_applied(): void
    {
        $driver = new OllamaEmbeddingDriver([]);

        $this->assertEquals('nomic-embed-text', $driver->getModelName());
        $this->assertEquals(768, $driver->getDimensions());
    }
}
