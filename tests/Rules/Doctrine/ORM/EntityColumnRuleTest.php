<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\DBAL\Types\Type;
use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\Descriptors\BigIntType;
use PHPStan\Type\Doctrine\Descriptors\BinaryType;
use PHPStan\Type\Doctrine\Descriptors\DateTimeImmutableType;
use PHPStan\Type\Doctrine\Descriptors\DateTimeType;
use PHPStan\Type\Doctrine\Descriptors\DateType;
use PHPStan\Type\Doctrine\Descriptors\IntegerType;
use PHPStan\Type\Doctrine\Descriptors\Ramsey\UuidTypeDescriptor;
use PHPStan\Type\Doctrine\Descriptors\ReflectionDescriptor;
use PHPStan\Type\Doctrine\Descriptors\StringType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use Ramsey\Uuid\Doctrine\UuidType;

/**
 * @extends RuleTestCase<EntityColumnRule>
 */
class EntityColumnRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		if (!Type::hasType(CustomType::NAME)) {
			Type::addType(CustomType::NAME, CustomType::class);
		}
		if (!Type::hasType(UuidType::NAME)) {
			Type::addType(UuidType::NAME, UuidType::class);
		}

		return new EntityColumnRule(
			new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null),
			new DescriptorRegistry([
				new BigIntType(),
				new StringType(),
				new DateTimeType(),
				new DateTimeImmutableType(),
				new BinaryType(),
				new IntegerType(),
				new ReflectionDescriptor(CustomType::class, $this->createBroker()),
				new DateType(),
				new UuidTypeDescriptor(UuidType::class),
			]),
			true
		);
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/MyBrokenEntity.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$id type mapping mismatch: database can contain string but property expects int|null.',
				19,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$one type mapping mismatch: database can contain string|null but property expects string.',
				25,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$two type mapping mismatch: property can contain string|null but database expects string.',
				31,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$three type mapping mismatch: database can contain DateTime but property expects DateTimeImmutable.',
				37,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$four type mapping mismatch: database can contain DateTimeImmutable but property expects DateTime.',
				43,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$four type mapping mismatch: property can contain DateTime but database expects DateTimeImmutable.',
				43,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$uuidInvalidType type mapping mismatch: database can contain Ramsey\Uuid\UuidInterface but property expects int.',
				72,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$uuidInvalidType type mapping mismatch: property can contain int but database expects Ramsey\Uuid\UuidInterface|string.',
				72,
			],
		]);
	}

	public function testSuperclass(): void
	{
		$this->analyse([__DIR__ . '/data/MyBrokenSuperclass.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenSuperclass::$five type mapping mismatch: database can contain resource but property expects int.',
				17,
			],
		]);
	}

	/**
	 * @dataProvider generatedIdsProvider
	 * @param string $file
	 * @param mixed[] $expectedErrors
	 */
	public function testGeneratedIds(string $file, array $expectedErrors): void
	{
		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @return \Iterator<string, mixed[]>
	 */
	public function generatedIdsProvider(): Iterator
	{
		yield 'not nullable' => [__DIR__ . '/data/GeneratedIdEntity1.php', []];
		yield 'nullable column' => [
			__DIR__ . '/data/GeneratedIdEntity2.php',
			[
				[
					'Property PHPStan\Rules\Doctrine\ORM\GeneratedIdEntity2::$id type mapping mismatch: database can contain string|null but property expects string.',
					19,
				],
			],
		];
		yield 'nullable property' => [__DIR__ . '/data/GeneratedIdEntity3.php', []];
		yield 'nullable both' => [__DIR__ . '/data/GeneratedIdEntity4.php', []];
	}

	public function testCustomType(): void
	{
		$this->analyse([__DIR__ . '/data/EntityWithCustomType.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\EntityWithCustomType::$foo type mapping mismatch: database can contain DateTimeInterface but property expects int.',
				24,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\EntityWithCustomType::$foo type mapping mismatch: property can contain int but database expects array.',
				24,
			],
		]);
	}

	public function testUnknownType(): void
	{
		$this->analyse([__DIR__ . '/data/EntityWithUnknownType.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\EntityWithUnknownType::$foo: Doctrine type "unknown" does not have any registered descriptor.',
				24,
			],
		]);
	}

}
