<?php

declare(strict_types=1);

namespace Belief\Hyperf\Database;

use Hyperf\Database\Model\Builder;
use Hyperf\DbConnection\Model\Model;
use Hyperf\Utils\Str;
use RuntimeException;

/**
 * Class Entity.
 */
class Entity extends Model
{
    /**
     * Whether to enable the specified encryption and decryption switch.
     * @var array
     */
    public $isNeedSign = false;

    /**
     * Array of fields to be encrypted and decrypted.
     * @var array
     */
    public $signColumns = [];

    /**
     * Signature key.
     * @var array
     */
    public $signkey = 'belief';

    /**
     * Create a new Model query builder for the model.
     *
     * @param \Hyperf\Database\Query\Builder $query
     * @return \Hyperf\Database\Model\Builder|static
     */
    public function newModelBuilder($query)
    {
        if (! $this->repository) {
            return new Builder($query);
        }
        if ($this->repository && class_exists($this->repository)) {
            $repository = $this->repository;
            $builder = new $repository($query);
            if ($builder instanceof Builder) {
                return $builder;
            }
        }
        throw new RuntimeException(sprintf('Cannot detect the repository of %s', $this->repository));
    }

    /**
     * set Repository Class Name.
     * @param $name
     */
    public function setRepositoryClassName($name)
    {
        $this->repository = $name;
    }

    /**
     * Set a given attribute on the model.
     * @param string $key
     * @param mixed $value
     * @return $this|Entity
     */
    public function setAttribute($key, $value)
    {
        //upgrade
        if ($this->isNeedSign) {
            //stand by set attribute
            if ($this->hasSetMutator($key)) {
                $value = $this->setMutatedAttributeValue($key, $value);
            }
            $value = in_array($key, $this->signColumns) ? $this->attributesEncrypt((string) $value, $this->signkey) : $value;
            $this->attributes[$key] = $value;
            return $this;
        }
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // the model, such as "json_encoding" an listing of data for storage.
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        if ($value && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($value);
        }

        if ($this->isClassCastable($key)) {
            $this->setClassCastableAttribute($key, $value);

            return $this;
        }

        if ($this->isJsonCastable($key) && ! is_null($value)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        // If this attribute contains a JSON ->, we'll set the proper value in the
        // attribute's underlying array. This takes care of properly nesting an
        // attribute in the array's value in the case of deeply nested items.
        if (Str::contains($key, '->')) {
            return $this->fillJsonAttribute($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        // If an attribute is a date, we will cast it to a string after converting it
        // to a DateTime / Carbon instance. This is so we will get some consistent
        // formatting while accessing attributes vs. arraying / JSONing a model.
        $attributes = $this->addDateAttributesToArray(
            $attributes = $this->getArrayableAttributes()
        );

        $attributes = $this->addMutatedAttributesToArray(
            $attributes,
            $mutatedAttributes = $this->getMutatedAttributes()
        );

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        $attributes = $this->addCastAttributesToArray(
            $attributes,
            $mutatedAttributes
        );

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }
        if ($this->isNeedSign) {
            foreach ($attributes as $key => $value) {
                if (in_array($key, $this->signColumns)) {
                    $attributes[$key] = $this->attributesDecrypt($value, $this->signkey);
                }
            }
        }
        return $attributes;
    }

    /**
     * @throws RuntimeException when the model does not define the repository class
     */
    public function getRepository()
    {
        return $this->newQuery();
    }

    /**
     * Attributes encrypt.
     * @param $str
     * @param string $key
     * @return string|string[]
     */
    private function attributesEncrypt($str, $key = '')
    {
        $coded = '';
        $keylength = strlen($key);

        for ($i = 0, $count = strlen($str); $i < $count; $i += $keylength) {
            $coded .= substr($str, $i, $keylength) ^ $key;
        }

        return str_replace('=', '', base64_encode($coded));
    }

    /**
     * Attributes decrypt.
     * @param $str
     * @param string $key
     * @return string
     */
    private function attributesDecrypt($str, $key = '')
    {
        $coded = '';
        $keylength = strlen($key);
        $str = base64_decode($str);

        for ($i = 0, $count = strlen($str); $i < $count; $i += $keylength) {
            $coded .= substr($str, $i, $keylength) ^ $key;
        }

        return $coded;
    }
}
