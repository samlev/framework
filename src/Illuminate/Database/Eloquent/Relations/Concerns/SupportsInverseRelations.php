<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Support\Str;

trait SupportsInverseRelations
{
    protected string|null $inverseRelationship = null;

    /**
     * Gets the name of the inverse relationship.
     *
     * @return string|null
     */
    public function getInverseRelationship()
    {
        return $this->inverseRelationship;
    }

    /**
     * Links the related models back to the parent after the query has run.
     *
     * @param  string|null  $relation
     * @return $this
     */
    public function inverse(?string $relation = null)
    {
        $relation ??= $this->guessInverseRelation();

        if (!$relation || !$this->getModel()->isRelation($relation)) {
            throw RelationNotFoundException::make($this->getModel(), $relation ?: 'null');
        }

        if ($this->inverseRelationship === null && $relation) {
            $this->query->afterQuery(function ($result) {
                return $this->inverseRelationship
                    ? $this->applyInverseRelationToCollection($result, $this->getParent())
                    : $result;
            });
        }

        $this->inverseRelationship = $relation;

        return $this;
    }

    /**
     * Removes the inverse relationship for this query.
     *
     * @return $this
     */
    public function withoutInverse()
    {
        $this->inverseRelationship = null;

        return $this;
    }

    /**
     * Gets possible inverse relations for the parent model.
     *
     * @return array<non-empty-string>
     */
    protected function getPossibleInverseRelations(): array
    {
        $possibleInverseRelations = [
            Str::camel(Str::beforeLast($this->getParent()->getForeignKey(), $this->getParent()->getKeyName())),
            Str::camel(class_basename($this->getParent())),
            'ownedBy',
            'owner',
        ];

        if (get_class($this->getParent()) === get_class($this->getModel())) {
            array_push($possibleInverseRelations, 'parent', 'ancestor');
        }

        return array_filter($possibleInverseRelations);
    }

    /**
     * Guesses the name of the inverse relationship.
     *
     * @return string|null
     */
    protected function guessInverseRelation(): string|null
    {
        return collect($this->getPossibleInverseRelations())
            ->filter()
            ->firstWhere(fn ($relation) => $this->getModel()->isRelation($relation));
    }

    /**
     * Sets the inverse relation on all models in a collection.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @param  \Illuminate\Database\Eloquent\Model|null  $parent
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function applyInverseRelationToCollection($models, ?Model $parent = null)
    {
        $parent ??= $this->getParent();

        foreach ($models as $model) {
            $this->applyInverseRelationToModel($model, $parent);
        }

        return $models;
    }

    /**
     * Sets the inverse relation on a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Illuminate\Database\Eloquent\Model|null  $parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function applyInverseRelationToModel(Model $model, ?Model $parent = null)
    {
        if ($inverse = $this->getInverseRelationship()) {
            $parent ??= $this->getParent();

            $model->setRelation($inverse, $parent);
        }

        return $model;
    }
}
