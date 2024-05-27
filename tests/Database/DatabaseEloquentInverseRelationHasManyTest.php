<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\TestCase;

class DatabaseEloquentInverseRelationHasManyTest extends TestCase
{
    /**
     * Setup the database schema.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $db = new DB;

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->bootEloquent();
        $db->setAsGlobal();

        $this->createSchema();
    }

    protected function createSchema()
    {
        $this->schema()->create('test_parent', function ($table) {
            $table->increments('id');
            $table->timestamps();
        });

        $this->schema()->create('test_child', function ($table) {
            $table->increments('id');
            $table->foreignId('parent_id');
            $table->timestamps();
        });
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('test_parent');
        $this->schema()->drop('test_child');
    }

    public function testHasManyInverseRelationIsProperlySetToParentWhenLazyLoaded()
    {
        HasManyInverseParentModel::factory(3)->create();
        $models = HasManyInverseParentModel::all();

        foreach ($models as $parent) {
            $this->assertFalse($parent->relationLoaded('children'));
            foreach ($parent->children as $child) {
                $this->assertTrue($child->relationLoaded('parent'));
                $this->assertSame($parent, $child->parent);
            }
        }
    }

    public function testHasManyInverseRelationIsProperlySetToParentWhenEagerLoaded()
    {
        HasManyInverseParentModel::factory(3)->create();

        $models = HasManyInverseParentModel::with('children')->get();

        foreach ($models as $parent) {
            foreach ($parent->children as $child) {
                $this->assertTrue($child->relationLoaded('parent'));
                $this->assertSame($parent, $child->parent);
            }
        }
    }

    public function testHasManyInverseRelationIsProperlySetToParentWhenMakingMany()
    {
        $parent = HasManyInverseParentModel::create();

        $children = $parent->children()->makeMany(array_fill(0, 3, []));

        foreach ($children as $child) {
            $this->assertTrue($child->relationLoaded('parent'));
            $this->assertSame($parent, $child->parent);
        }
    }

    public function testHasManyInverseRelationIsProperlySetToParentWhenCreatingMany()
    {
        $parent = HasManyInverseParentModel::create();

        $children = $parent->children()->createMany(array_fill(0, 3, []));

        foreach ($children as $child) {
            $this->assertTrue($child->relationLoaded('parent'));
            $this->assertSame($parent, $child->parent);
        }
    }

    public function testHasManyInverseRelationIsProperlySetToParentWhenCreatingManyQuietly()
    {
        $parent = HasManyInverseParentModel::create();

        $children = $parent->children()->createManyQuietly(array_fill(0, 3, []));

        foreach ($children as $child) {
            $this->assertTrue($child->relationLoaded('parent'));
            $this->assertSame($parent, $child->parent);
        }
    }

    public function testHasManyInverseRelationIsProperlySetToParentWhenSavingMany()
    {
        $parent = HasManyInverseParentModel::create();

        $children = array_fill(0, 3, new HasManyInverseChildModel);

        $parent->children()->saveMany($children);

        foreach ($children as $child) {
            $this->assertTrue($child->relationLoaded('parent'));
            $this->assertSame($parent, $child->parent);
        }
    }

    public function testHasManyInverseRelationIsProperlySetToParentWhenUpdatingMany()
    {
        $parent = HasManyInverseParentModel::create();

        $children = HasManyInverseChildModel::factory()->count(3)->create();

        foreach ($children as $child) {
            $this->assertTrue($parent->isNot($child->parent));
        }

        $parent->children()->saveMany($children);

        foreach ($children as $child) {
            $this->assertSame($parent, $child->parent);
        }
    }

    /**
     * Helpers...
     */

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function connection($connection = 'default')
    {
        return Eloquent::getConnectionResolver()->connection($connection);
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function schema($connection = 'default')
    {
        return $this->connection($connection)->getSchemaBuilder();
    }
}

class HasManyInverseParentModel extends Model
{
    use HasFactory;

    protected $table = 'test_parent';
    protected $fillable = ['id'];

    protected static function newFactory()
    {
        return new HasManyInverseParentModelFactory();
    }

    public function children(): HasMany
    {
        return $this->hasMany(HasManyInverseChildModel::class, 'parent_id')->inverse('parent');
    }
}

class HasManyInverseParentModelFactory extends Factory
{
    protected $model = HasManyInverseParentModel::class;
    public function definition()
    {
        $this->has(HasManyInverseChildModel::factory()->count(3));

        return [];
    }
}

class HasManyInverseChildModel extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return new HasManyInverseChildModelFactory();
    }

    protected $table = 'test_child';
    protected $fillable = ['id', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(HasManyInverseParentModel::class, 'parent_id');
    }
}

class HasManyInverseChildModelFactory extends Factory
{
    protected $model = HasManyInverseChildModel::class;
    public function definition()
    {
        return [
            'parent_id' => HasManyInverseParentModel::factory(),
        ];
    }
}
