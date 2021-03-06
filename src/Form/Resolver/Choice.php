<?php

namespace Bolt\Form\Resolver;

use Bolt\Storage\Entity\Content;
use Bolt\Storage\EntityManager;
use Bolt\Storage\Mapping\ContentType;
use Bolt\Storage\Query\Query;
use Bolt\Storage\Query\QueryResultset;

/**
 * Choice resolver.
 *
 * @internal DO NOT USE. Will be replaced circa Bolt 3.5.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class Choice
{
    /** @var EntityManager */
    private $em;
    /** @var Query */
    private $query;

    /**
     * Constructor.
     *
     * @param EntityManager $em
     * @param Query         $query
     */
    public function __construct(EntityManager $em, Query $query)
    {
        $this->em = $em;
        $this->query = $query;
    }

    /**
     * @param ContentType $contentType
     * @param array       $templateFields
     *
     * @return array
     */
    public function get(ContentType $contentType, array $templateFields)
    {
        $select = null;
        foreach ($contentType->getFields() as $name => $field) {
            $values = $this->getValues($field);
            if ($values !== null) {
                $select[$name] = $values;
            }
        }

        foreach ($templateFields as $name => $field) {
            $values = $this->getValues($field);
            if ($values !== null) {
                $select['templatefields'][$name] = $values;
            }
        }

        return $select;
    }

    /**
     * @param array $field
     *
     * @return array|null
     */
    private function getValues(array $field)
    {
        if ($field['type'] !== 'select') {
            return null;
        }
        $field += [
            'values'   => [],
            'limit'    => null,
            'sortable' => false,
            'filter'   => null,
        ];
        $values = $field['values'];
        $limit = $field['limit'];
        $sortable = $field['sortable'];
        $filter = $field['filter'];

        // Get the appropriate values
        return is_string($values)
            ? $this->getEntityValues($values, $limit, $sortable, $filter)
            : $this->getYamlValues($values, $limit, $sortable)
        ;
    }

    /**
     * Return a YAML defined array of select field value options.
     *
     * @param array $values
     * @param int   $limit
     * @param bool  $sortable
     *
     * @return array
     */
    private function getYamlValues(array $values, $limit, $sortable)
    {
        if ($values !== null) {
            $values = array_slice($values, 0, $limit);
        }
        if ($sortable) {
            asort($values, SORT_REGULAR);
        }

        return $values;
    }

    /**
     * Return select field value options from a ContentType's records.
     *
     * @param string $queryString
     * @param int    $limit
     * @param bool   $sortable
     * @param array  $filter
     *
     * @return array
     */
    private function getEntityValues($queryString, $limit, $sortable, $filter)
    {
        $baseParts = explode('/', $queryString);
        if (count($baseParts) < 2) {
            throw new \InvalidArgumentException(sprintf('The "values" key for a ContentType select must be in the form of ContentType/field_name but "%s" given', $queryString));
        }

        $contentType = $baseParts[0];
        $queryFields = explode(',', $baseParts[1]);
        foreach ($queryFields as $queryField) {
            if ($queryField === '') {
                throw new \InvalidArgumentException(sprintf('The "values" key for a ContentType select must include a single field, or comma separated fields, "%s" given', $queryString));
            }
        }
        $orderBy = $sortable ? [$queryFields[0], 'ASC'] : ['id', 'ASC'];

        $values = [];
        if ($filter === null) {
            $repo = $this->em->getRepository($contentType);
            $entities = $repo->findBy([], $orderBy, $limit);
        } else {
            /** @var QueryResultset $entities */
            $entities = $this->query->getContent('pages', $filter);
        }
        if (!$entities) {
            return $values;
        }

        /** @var Content $entity */
        foreach ($entities as $entity) {
            $id = $entity->getId();
            $values[$id] = $entity->get($queryFields[0]);
            if (isset($queryFields[1])) {
                $values[$id] .= ' / ' . $entity->get($queryFields[1]);
            }
        }

        return $values;
    }
}
