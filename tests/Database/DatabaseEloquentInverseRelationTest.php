<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsInverseRelations;
use Illuminate\Database\Eloquent\Relations\Relation;
use Mockery as m;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatabaseEloquentInverseRelationTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testBuilderCallbackIsNotAppliedWhenInverseRelationIsNotSet()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());
        $builder->shouldReceive('afterQuery')->never();

        new HasInverseRelationStub($builder, new HasInverseRelationParentStub());
    }

    public function testBuilderCallbackIsNotSetIfInverseRelationIsEmptyString()
    {
        $builder = m::mock(Builder::class);

        $this->expectException(RelationNotFoundException::class);
        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());
        $builder->shouldReceive('afterQuery')->never();

        (new HasInverseRelationStub($builder, new HasInverseRelationParentStub()))->inverse('');
    }

    public function testBuilderCallbackIsNotSetIfInverseRelationshipDoesNotExist()
    {
        $builder = m::mock(Builder::class);

        $this->expectException(RelationNotFoundException::class);
        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());
        $builder->shouldReceive('afterQuery')->never();

        (new HasInverseRelationStub($builder, new HasInverseRelationParentStub()))->inverse('foo');
    }

    public function testWithoutInverseMethodRemovesInverseRelation()
    {
        $builder = m::mock(Builder::class);

        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());
        $builder->shouldReceive('afterQuery')->once()->andReturnSelf();

        $relation = (new HasInverseRelationStub($builder, new HasInverseRelationParentStub()));
        $this->assertNull($relation->getInverseRelationship());

        $relation->inverse('test');
        $this->assertSame('test', $relation->getInverseRelationship());

        $relation->withoutInverse();
        $this->assertNull($relation->getInverseRelationship());
    }

    public function testBuilderCallbackIsAppliedWhenInverseRelationIsSet()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());

        $parent = new HasInverseRelationParentStub();
        $builder->shouldReceive('afterQuery')->withArgs(function (\Closure $callback) use ($parent) {
            $relation = (new \ReflectionFunction($callback))->getClosureThis();

            return $relation instanceof HasInverseRelationStub && $relation->getParent() === $parent;
        })->once()->andReturnSelf();

        (new HasInverseRelationStub($builder, $parent))->inverse('test');
    }

    public function testBuilderCallbackAppliesInverseRelationToAllModelsInResult()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());

        // Capture the callback so that we can manually call it.
        $afterQuery = null;
        $builder->shouldReceive('afterQuery')->withArgs(function (\Closure $callback) use (&$afterQuery) {
            return (bool)$afterQuery = $callback;
        })->once()->andReturnSelf();

        $parent = new HasInverseRelationParentStub();
        (new HasInverseRelationStub($builder, $parent))->inverse('test');

        $results = new Collection(array_fill(0, 5, new HasInverseRelationRelatedStub()));

        foreach ($results as $model) {
            $this->assertEmpty($model->getRelations());
            $this->assertFalse($model->relationLoaded('test'));
        }

        $results = $afterQuery($results);

        foreach ($results as $model) {
            $this->assertNotEmpty($model->getRelations());
            $this->assertTrue($model->relationLoaded('test'));
            $this->assertSame($parent, $model->test);
        }
    }

    public function testInverseRelationIsNotSetIfInverseRelationIsUnset()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());

        // Capture the callback so that we can manually call it.
        $afterQuery = null;
        $builder->shouldReceive('afterQuery')->withArgs(function (\Closure $callback) use (&$afterQuery) {
            return (bool)$afterQuery = $callback;
        })->once()->andReturnSelf();

        $parent = new HasInverseRelationParentStub();
        $relation = (new HasInverseRelationStub($builder, $parent));
        $relation->inverse('test');

        $results = new Collection(array_fill(0, 5, new HasInverseRelationRelatedStub()));
        foreach ($results as $model) {
            $this->assertEmpty($model->getRelations());
        }
        $results = $afterQuery($results);
        foreach ($results as $model) {
            $this->assertNotEmpty($model->getRelations());
            $this->assertSame($parent, $model->getRelation('test'));
        }

        // Reset the inverse relation
        $relation->withoutInverse();

        $results = new Collection(array_fill(0, 5, new HasInverseRelationRelatedStub()));
        foreach ($results as $model) {
            $this->assertEmpty($model->getRelations());
        }
        foreach ($results as $model) {
            $this->assertEmpty($model->getRelations());
        }
    }

    public function testProvidesPossibleRelationBasedOnParent()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn(new HasOneInverseChildModel);

        $parent = new HasInverseRelationParentStub;
        $relation = (new HasInverseRelationStub($builder, $parent));

        $possibleRelations = ['parentStub', 'hasInverseRelationParentStub', 'ownedBy', 'owner'];
        $this->assertSame($possibleRelations, $relation->exposeGetPossibleInverseRelations());
    }

    public function testProvidesPossibleRecursiveRelationsIfRelatedIsTheSameClassAsParent()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationParentStub);

        $parent = new HasInverseRelationParentStub;
        $relation = (new HasInverseRelationStub($builder, $parent));

        $possibleRelations = ['parentStub', 'hasInverseRelationParentStub', 'ownedBy', 'owner', 'parent', 'ancestor'];
        $this->assertSame($possibleRelations, $relation->exposeGetPossibleInverseRelations());
    }

    public function testDoesNotProvidePossibleRecursiveRelationsIfRelatedIsNotTheSameClassAsParent()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn(new HasOneInverseChildModel);

        $parent = new HasInverseRelationParentStub;
        $relation = (new HasInverseRelationStub($builder, $parent));

        $possibleRelations = ['parentStub', 'hasInverseRelationParentStub', 'ownedBy', 'owner'];
        $this->assertSame($possibleRelations, $relation->exposeGetPossibleInverseRelations());
    }

    public function testDoesNotProvidePossibleRecursiveRelationsIfRelatedClassIsAncestorOfParent()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationParentStub);

        $parent = new HasInverseRelationParentSubclassStub;
        $relation = (new HasInverseRelationStub($builder, $parent));

        $possibleRelations = ['parentStub', 'hasInverseRelationParentSubclassStub', 'ownedBy', 'owner'];
        $this->assertSame($possibleRelations, $relation->exposeGetPossibleInverseRelations());
    }

    public function testDoesNotProvidePossibleRecursiveRelationsIfRelatedClassIsSubclassOfParent()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationParentSubclassStub);

        $parent = new HasInverseRelationParentStub;
        $relation = (new HasInverseRelationStub($builder, $parent));

        $possibleRelations = ['parentStub', 'hasInverseRelationParentStub', 'ownedBy', 'owner'];
        $this->assertSame($possibleRelations, $relation->exposeGetPossibleInverseRelations());
    }

    #[DataProvider('guessedParentRelationsDataProvider')]
    public function testGuessesInverseRelationBasedOnParent($guessedRelation)
    {
        $builder = m::mock(Builder::class);
        $related = m::mock(Model::class);
        $builder->shouldReceive('getModel')->andReturn($related);

        $parent = new HasInverseRelationParentStub;
        $related->shouldReceive('isRelation')->andReturnUsing(fn($relation) => $relation === $guessedRelation);
        $relation = (new HasInverseRelationStub($builder, $parent));

        $this->assertSame($guessedRelation, $relation->exposeGuessInverseRelation());
    }

    #[DataProvider('guessedRecursiveRelationsDataProvider')]
    public function testGuessesRecursiveInverseRelationsIfRelatedIsSameClassAsParent($guessedRelation)
    {
        $builder = m::mock(Builder::class);
        $related = m::mock(Model::class);
        $builder->shouldReceive('getModel')->andReturn($related);

        $parent = clone $related;
        $parent->shouldReceive('getForeignKey')->andReturn('recursive_parent_id');
        $parent->shouldReceive('getKeyName')->andReturn('id');
        $related->shouldReceive('isRelation')->andReturnUsing(fn($relation) => $relation === $guessedRelation);

        $relation = (new HasInverseRelationStub($builder, $parent));

        $this->assertSame($guessedRelation, $relation->exposeGuessInverseRelation());
    }

    #[DataProvider('guessedRecursiveRelationsDataProvider')]
    public function testDoesNotGuessRecursiveInverseRelationsIfRelatedIsNotSameClassAsParent($guessedRelation)
    {
        $builder = m::mock(Builder::class);
        $related = m::mock(Model::class);
        $builder->shouldReceive('getModel')->andReturn($related);

        $related->shouldReceive('isRelation')->andReturn(false);
        $related->shouldReceive('isRelation')->with($guessedRelation)->never();

        $relation = new HasInverseRelationStub($builder, new HasInverseRelationParentStub);

        $this->assertNull($relation->exposeGuessInverseRelation());
    }

    #[DataProvider('guessedParentRelationsDataProvider')]
    public function testSetsGuessedInverseRelationBasedOnParent($guessedRelation)
    {
        $builder = m::mock(Builder::class);
        $related = m::mock(Model::class);
        $builder->shouldReceive('getModel')->andReturn($related);

        $parent = new HasInverseRelationParentStub;
        $builder->shouldReceive('afterQuery')->once()->andReturnSelf();
        $related->shouldReceive('isRelation')->andReturnUsing(fn($relation) => $relation === $guessedRelation);
        $relation = (new HasInverseRelationStub($builder, $parent))->inverse();

        $this->assertSame($guessedRelation, $relation->getInverseRelationship());
    }

    #[DataProvider('guessedRecursiveRelationsDataProvider')]
    public function testDoesNotSetRecursiveInverseRelationsIfRelatedIsNotSameClassAsParent($guessedRelation)
    {
        $builder = m::mock(Builder::class);
        $related = m::mock(Model::class);
        $builder->shouldReceive('getModel')->andReturn($related);

        $parent = new HasInverseRelationParentStub;
        $builder->shouldReceive('afterQuery')->never();
        foreach (self::guessedParentRelationsDataProvider() as $notRelated) {
            $related->shouldReceive('isRelation')->with($notRelated[0])->once()->andReturn(false);
        }

        $related->shouldReceive('isRelation')->with($guessedRelation)->never();
        $this->expectException(RelationNotFoundException::class);
        $relation = (new HasInverseRelationStub($builder, $parent))->inverse();

        $this->assertNull($relation->getInverseRelationship());
    }

    public static function guessedParentRelationsDataProvider()
    {
        yield ['parentStub'];
        yield ['hasInverseRelationParentStub'];
        yield ['ownedBy'];
        yield ['owner'];
    }

    public static function guessedRecursiveRelationsDataProvider()
    {
        yield ['parent'];
        yield ['ancestor'];
    }
}

class HasInverseRelationParentStub extends Model
{
    protected static $unguarded = true;
    protected $primaryKey = 'id';

    public function getForeignKey()
    {
        return 'parent_stub_id';
    }
}
class HasInverseRelationParentSubclassStub extends HasInverseRelationParentStub
{
}

class HasInverseRelationRelatedStub extends Model
{
    protected static $unguarded = true;
    protected $primaryKey = 'id';

    public function getForeignKey()
    {
        return 'child_stub_id';
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(HasInverseRelationParentStub::class);
    }
}

class HasInverseRelationStub extends Relation
{
    use SupportsInverseRelations;

    // None of these methods will actually be called - they're just needed to fill out `Relation`
    public function match(array $models, Collection $results, $relation)
    {
        return $models;
    }

    public function initRelation(array $models, $relation)
    {
        return $models;
    }

    public function getResults()
    {
        return $this->query->get();
    }

    public function addConstraints()
    {
        //
    }

    public function addEagerConstraints(array $models)
    {
        //
    }

    // Expose access to protected methods for testing
    public function exposeGetPossibleInverseRelations(): array
    {
        return $this->getPossibleInverseRelations();
    }

    public function exposeGuessInverseRelation(): string|null
    {
        return $this->guessInverseRelation();
    }
}
