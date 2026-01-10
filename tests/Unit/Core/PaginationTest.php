<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Core\Pagination;

final class PaginationTest extends TestCase
{
    public function testCreateWithValidParameters(): void
    {
        $pagination = new Pagination(page: 1, perPage: 10);

        $this->assertSame(1, $pagination->page);
        $this->assertSame(10, $pagination->perPage);
    }

    public function testDefaultFactoryMethod(): void
    {
        $pagination = Pagination::default();

        $this->assertSame(1, $pagination->page);
        $this->assertSame(10, $pagination->perPage);
    }

    public function testFactoryMethod(): void
    {
        $pagination = Pagination::create(page: 2, perPage: 25);

        $this->assertSame(2, $pagination->page);
        $this->assertSame(25, $pagination->perPage);
    }

    public function testGetOffsetForFirstPage(): void
    {
        $pagination = new Pagination(page: 1, perPage: 10);

        $this->assertSame(0, $pagination->getOffset());
    }

    public function testGetOffsetForSecondPage(): void
    {
        $pagination = new Pagination(page: 2, perPage: 10);

        $this->assertSame(10, $pagination->getOffset());
    }

    public function testGetOffsetForThirdPage(): void
    {
        $pagination = new Pagination(page: 3, perPage: 25);

        $this->assertSame(50, $pagination->getOffset());
    }

    public function testGetLimit(): void
    {
        $pagination = new Pagination(page: 1, perPage: 50);

        $this->assertSame(50, $pagination->getLimit());
    }

    public function testToQueryParams(): void
    {
        $pagination = new Pagination(page: 3, perPage: 25);

        $queryParams = $pagination->toQueryParams();

        $this->assertSame(['page' => 3, 'per_page' => 25], $queryParams);
        $this->assertIsArray($queryParams);
        $this->assertArrayHasKey('page', $queryParams);
        $this->assertArrayHasKey('per_page', $queryParams);
        $this->assertIsInt($queryParams['page']);
        $this->assertIsInt($queryParams['per_page']);
    }

    public function testToHeaders(): void
    {
        $pagination = new Pagination(page: 5, perPage: 100);

        $headers = $pagination->toHeaders();

        $this->assertSame(['page' => '5', 'per_page' => '100'], $headers);
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('page', $headers);
        $this->assertArrayHasKey('per_page', $headers);
        $this->assertIsString($headers['page']);
        $this->assertIsString($headers['per_page']);
    }

    public function testThrowsExceptionWhenPageIsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be greater than or equal to 1');

        new Pagination(page: 0, perPage: 10);
    }

    public function testThrowsExceptionWhenPageIsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be greater than or equal to 1');

        new Pagination(page: -1, perPage: 10);
    }

    public function testThrowsExceptionWhenPerPageIsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Per page must be greater than or equal to 1');

        new Pagination(page: 1, perPage: 0);
    }

    public function testThrowsExceptionWhenPerPageIsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Per page must be greater than or equal to 1');

        new Pagination(page: 1, perPage: -5);
    }

    public function testAcceptsPageValueOfOne(): void
    {
        $pagination = new Pagination(page: 1, perPage: 10);

        $this->assertSame(1, $pagination->page);
    }

    public function testAcceptsPerPageValueOfOne(): void
    {
        $pagination = new Pagination(page: 1, perPage: 1);

        $this->assertSame(1, $pagination->perPage);
    }

    public function testLargePageAndPerPageValues(): void
    {
        $pagination = new Pagination(page: 1000, perPage: 500);

        $this->assertSame(1000, $pagination->page);
        $this->assertSame(500, $pagination->perPage);
        $this->assertSame(499500, $pagination->getOffset());
    }

    public function testReadonlyProperties(): void
    {
        $pagination = new Pagination(page: 2, perPage: 20);

        $this->assertSame(2, $pagination->page);
        $this->assertSame(20, $pagination->perPage);

        $offset = $pagination->getOffset();
        $limit = $pagination->getLimit();
        $queryParams = $pagination->toQueryParams();
        $headers = $pagination->toHeaders();

        $this->assertSame(20, $offset);
        $this->assertSame(20, $limit);
        $this->assertSame(['page' => 2, 'per_page' => 20], $queryParams);
        $this->assertSame(['page' => '2', 'per_page' => '20'], $headers);
    }
}
