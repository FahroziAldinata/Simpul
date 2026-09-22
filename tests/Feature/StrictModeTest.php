<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('lazy loading relations throws LazyLoadingViolationException in non-production when iterating models', function () {
    Schema::create('strict_test_parents', function (Blueprint $table) {
        $table->id();
    });

    Schema::create('strict_test_children', function (Blueprint $table) {
        $table->id();
        $table->foreignId('parent_id');
    });

    $childClass = new class extends Model
    {
        protected $table = 'strict_test_children';

        public $timestamps = false;

        protected $guarded = [];
    };

    $childClassName = get_class($childClass);

    $parentClass = new class extends Model
    {
        protected $table = 'strict_test_parents';

        public $timestamps = false;

        protected $guarded = [];

        public static string $childClass;

        public function children()
        {
            return $this->hasMany(self::$childClass, 'parent_id');
        }
    };

    $parentClass::$childClass = $childClassName;

    // Create 2 parents so collection count > 1 triggers N+1 prevention
    $parentClass::query()->create([]);
    $parentClass::query()->create([]);

    $parents = $parentClass::all();

    expect(fn () => $parents->first()->children)->toThrow(LazyLoadingViolationException::class);
});
