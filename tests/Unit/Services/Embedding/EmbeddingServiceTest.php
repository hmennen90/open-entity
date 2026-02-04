<?php

namespace Tests\Unit\Services\Embedding;

use App\Services\Embedding\EmbeddingService;
use App\Services\Embedding\Contracts\EmbeddingDriverInterface;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Mockery;

class EmbeddingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_embed_delegates_to_driver(): void
    {
        $expectedEmbedding = array_fill(0, 768, 0.1);

        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $driver->shouldReceive('embed')
            ->with('test text')
            ->once()
            ->andReturn($expectedEmbedding);

        $service = new EmbeddingService($driver);

        $result = $service->embed('test text');

        $this->assertEquals($expectedEmbedding, $result);
    }

    public function test_embed_falls_back_to_secondary_driver_on_failure(): void
    {
        $expectedEmbedding = array_fill(0, 1536, 0.2);

        $primaryDriver = Mockery::mock(EmbeddingDriverInterface::class);
        $primaryDriver->shouldReceive('embed')
            ->with('test text')
            ->once()
            ->andThrow(new \RuntimeException('Primary driver failed'));

        $fallbackDriver = Mockery::mock(EmbeddingDriverInterface::class);
        $fallbackDriver->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);
        $fallbackDriver->shouldReceive('embed')
            ->with('test text')
            ->once()
            ->andReturn($expectedEmbedding);

        $service = new EmbeddingService($primaryDriver, $fallbackDriver);

        $result = $service->embed('test text');

        $this->assertEquals($expectedEmbedding, $result);
    }

    public function test_cosine_similarity_identical_vectors(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $vector = [0.5, 0.5, 0.5, 0.5];

        $similarity = $service->cosineSimilarity($vector, $vector);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_orthogonal_vectors(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $vectorA = [1.0, 0.0, 0.0];
        $vectorB = [0.0, 1.0, 0.0];

        $similarity = $service->cosineSimilarity($vectorA, $vectorB);

        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_opposite_vectors(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $vectorA = [1.0, 0.0, 0.0];
        $vectorB = [-1.0, 0.0, 0.0];

        $similarity = $service->cosineSimilarity($vectorA, $vectorB);

        $this->assertEqualsWithDelta(-1.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_throws_on_dimension_mismatch(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vector dimension mismatch');

        $service->cosineSimilarity([1.0, 2.0], [1.0, 2.0, 3.0]);
    }

    public function test_find_similar_returns_sorted_by_similarity(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $queryEmbedding = [1.0, 0.0, 0.0];

        $candidates = collect([
            (object) ['id' => 1, 'embedding' => [0.9, 0.1, 0.0]],  // Very similar
            (object) ['id' => 2, 'embedding' => [0.0, 1.0, 0.0]],  // Orthogonal
            (object) ['id' => 3, 'embedding' => [0.7, 0.7, 0.0]],  // Somewhat similar
        ]);

        $results = $service->findSimilar($queryEmbedding, $candidates, 3, 0.0);

        $this->assertEquals(1, $results->first()->id);
        $this->assertEquals(3, $results->skip(1)->first()->id);
        $this->assertEquals(2, $results->last()->id);
    }

    public function test_find_similar_respects_threshold(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $queryEmbedding = [1.0, 0.0, 0.0];

        $candidates = collect([
            (object) ['id' => 1, 'embedding' => [0.95, 0.05, 0.0]],  // Very similar
            (object) ['id' => 2, 'embedding' => [0.0, 1.0, 0.0]],   // Orthogonal
        ]);

        $results = $service->findSimilar($queryEmbedding, $candidates, 10, 0.8);

        $this->assertCount(1, $results);
        $this->assertEquals(1, $results->first()->id);
    }

    public function test_find_similar_respects_k_limit(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $queryEmbedding = [1.0, 0.0, 0.0];

        $candidates = collect([
            (object) ['id' => 1, 'embedding' => [0.9, 0.1, 0.0]],
            (object) ['id' => 2, 'embedding' => [0.8, 0.2, 0.0]],
            (object) ['id' => 3, 'embedding' => [0.7, 0.3, 0.0]],
            (object) ['id' => 4, 'embedding' => [0.6, 0.4, 0.0]],
        ]);

        $results = $service->findSimilar($queryEmbedding, $candidates, 2, 0.0);

        $this->assertCount(2, $results);
    }

    public function test_encode_and_decode_binary(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $original = [0.1, 0.2, 0.3, -0.5, 1.0];

        $binary = $service->encodeToBinary($original);
        $decoded = $service->decodeBinaryEmbedding($binary);

        $this->assertCount(count($original), $decoded);

        foreach ($original as $i => $value) {
            $this->assertEqualsWithDelta($value, $decoded[$i], 0.0001);
        }
    }

    public function test_decode_empty_binary_returns_empty_array(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $result = $service->decodeBinaryEmbedding('');

        $this->assertEquals([], $result);
    }

    public function test_get_model_name_delegates_to_driver(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $driver->shouldReceive('getModelName')
            ->once()
            ->andReturn('nomic-embed-text');

        $service = new EmbeddingService($driver);

        $this->assertEquals('nomic-embed-text', $service->getModelName());
    }

    public function test_get_dimensions_delegates_to_driver(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $driver->shouldReceive('getDimensions')
            ->once()
            ->andReturn(768);

        $service = new EmbeddingService($driver);

        $this->assertEquals(768, $service->getDimensions());
    }

    public function test_is_available_checks_primary_driver(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $driver->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $service = new EmbeddingService($driver);

        $this->assertTrue($service->isAvailable());
    }

    public function test_is_available_checks_fallback_when_primary_unavailable(): void
    {
        $primaryDriver = Mockery::mock(EmbeddingDriverInterface::class);
        $primaryDriver->shouldReceive('isAvailable')
            ->once()
            ->andReturn(false);

        $fallbackDriver = Mockery::mock(EmbeddingDriverInterface::class);
        $fallbackDriver->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $service = new EmbeddingService($primaryDriver, $fallbackDriver);

        $this->assertTrue($service->isAvailable());
    }

    public function test_find_similar_handles_binary_embeddings(): void
    {
        $driver = Mockery::mock(EmbeddingDriverInterface::class);
        $service = new EmbeddingService($driver);

        $queryEmbedding = [1.0, 0.0, 0.0];

        // Binary-encoded embedding (like from database BLOB)
        $binaryEmbedding = pack('f*', 0.9, 0.1, 0.0);

        $candidates = collect([
            (object) ['id' => 1, 'embedding' => $binaryEmbedding],
        ]);

        $results = $service->findSimilar($queryEmbedding, $candidates, 10, 0.0);

        $this->assertCount(1, $results);
        $this->assertEquals(1, $results->first()->id);
    }
}
