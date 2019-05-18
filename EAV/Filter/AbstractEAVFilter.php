<?php /** @noinspection PhpInternalEntityUsedInspection */
/*
 * This file is part of the CleverAge/EAVApiPlatform package.
 *
 * Copyright (c) 2015-2019 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\EAVApiPlatformBundle\EAV\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Util\RequestParser;
use CleverAge\EAVApiPlatformBundle\Resolver\FamilyResolver;
use Doctrine\ORM\QueryBuilder;
use Sidus\EAVFilterBundle\Filter\EAVFilterHelper;
use Sidus\EAVModelBundle\Doctrine\AttributeQueryBuilderInterface;
use Sidus\EAVModelBundle\Doctrine\DQLHandlerInterface;
use Sidus\EAVModelBundle\Doctrine\EAVQueryBuilder;
use Sidus\EAVModelBundle\Doctrine\EAVQueryBuilderInterface;
use Sidus\EAVModelBundle\Entity\DataInterface;
use Sidus\EAVModelBundle\Exception\MissingAttributeException;
use Sidus\EAVModelBundle\Exception\MissingFamilyException;
use Sidus\EAVModelBundle\Model\AttributeInterface;
use Sidus\EAVModelBundle\Model\FamilyInterface;
use Sidus\EAVModelBundle\Registry\FamilyRegistry;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter as BaseSearchFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Abstract class with helpers for easing the implementation of a filter.
 *
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
abstract class AbstractEAVFilter implements FilterInterface
{
    /** @var RequestStack */
    protected $requestStack;

    /** @var FamilyRegistry */
    protected $familyRegistry;

    /** @var FamilyResolver */
    protected $familyResolver;

    /** @var EAVFilterHelper */
    protected $eavFilterHelper;

    /** @var array */
    protected $supportedTypes;

    /** @var array */
    protected $properties;

    /** @var string */
    protected $familyCode;

    /**
     * @param RequestStack    $requestStack
     * @param FamilyRegistry  $familyRegistry
     * @param FamilyResolver  $familyResolver
     * @param EAVFilterHelper $eavFilterHelper
     * @param array           $supportedTypes
     * @param array           $properties
     * @param string          $familyCode
     */
    public function __construct(
        RequestStack $requestStack,
        FamilyRegistry $familyRegistry,
        FamilyResolver $familyResolver,
        EAVFilterHelper $eavFilterHelper,
        array $supportedTypes,
        array $properties = null,
        $familyCode = null
    ) {
        $this->requestStack = $requestStack;
        $this->familyRegistry = $familyRegistry;
        $this->familyResolver = $familyResolver;
        $this->eavFilterHelper = $eavFilterHelper;
        $this->supportedTypes = $supportedTypes;
        $this->properties = $properties;
        $this->familyCode = $familyCode;
    }

    /**
     * {@inheritDoc}
     * @throws MissingFamilyException
     * @throws \UnexpectedValueException
     * @throws \LogicException
     */
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null
    ): void {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $this->doApply($queryBuilder, $this->extractProperties($request), $resourceClass, $operationName);
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->properties;
        if (null === $properties) {
            $family = $this->getFamily($resourceClass);
            $properties = array_fill_keys(array_keys($family->getAttributes()), null);
        }

        foreach ($properties as $property => $strategy) {
            try {
                $attribute = $this->getAttribute($resourceClass, $property);
            } catch (MissingAttributeException $e) {
                continue;
            }
            $typeOfField = $this->getType($attribute);
            if (!\in_array($typeOfField, $this->supportedTypes, true)) {
                continue;
            }

            $this->appendFilterDescription($description, $attribute, $property, $typeOfField, $strategy);
        }

        return $description;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array        $properties
     * @param string       $resourceClass
     * @param string|null  $operationName
     *
     * @throws MissingFamilyException
     * @throws \UnexpectedValueException
     * @throws \LogicException
     */
    protected function doApply(
        QueryBuilder $queryBuilder,
        array $properties,
        string $resourceClass,
        string $operationName = null
    ): void {
        $eavQB = new EAVQueryBuilder($queryBuilder, 'o');
        $dqlHandlers = [];
        foreach ($properties as $property => $value) {
            if (null !== $this->properties && !array_key_exists($property, $this->properties)) {
                continue;
            }

            $family = $this->getFamily($resourceClass);
            $attributeQueryBuilder = $this->eavFilterHelper->getEAVAttributeQueryBuilder($eavQB, $family, $property);
            $dqlHandler = $this->filterAttribute(
                $eavQB,
                $attributeQueryBuilder,
                $value,
                $this->properties[$property] ?? null,
                $operationName
            );
            if ($dqlHandler instanceof DQLHandlerInterface) {
                $dqlHandlers[] = $dqlHandler;
            }
        }

        $eavQB->apply($eavQB->getAnd($dqlHandlers));
    }

    /**
     * Passes a property through the filter.
     *
     * @param EAVQueryBuilderInterface       $eavQb
     * @param AttributeQueryBuilderInterface $attributeQueryBuilder ,
     * @param mixed                          $value
     * @param null                           $strategy
     * @param string|null                    $operationName
     *
     * @return DQLHandlerInterface|null
     */
    abstract protected function filterAttribute(
        EAVQueryBuilderInterface $eavQb,
        AttributeQueryBuilderInterface $attributeQueryBuilder,
        $value,
        $strategy = null,
        string $operationName = null
    ): ?DQLHandlerInterface;

    /**
     * Extracts properties to filter from the request.
     *
     * @param Request $request
     *
     * @return array
     */
    protected function extractProperties(Request $request): array
    {
        $needsFixing = false;

        if (null !== $this->properties) {
            foreach ($this->properties as $property => $value) {
                if ($this->isPropertyNested($property) && $request->query->has(str_replace('.', '_', $property))) {
                    $needsFixing = true;
                }
            }
        }

        if ($needsFixing) {
            $request = RequestParser::parseAndDuplicateRequest($request);
        }

        return $request->query->all();
    }

    /**
     * Determines whether the given property is nested.
     *
     * @param string $property
     *
     * @return bool
     */
    protected function isPropertyNested(string $property): bool
    {
        return false !== strpos($property, '.');
    }

    /**
     * @param array              $description
     * @param AttributeInterface $attribute
     * @param string             $property
     * @param string             $typeOfField
     * @param string             $strategy
     */
    protected function appendFilterDescription(
        array &$description,
        AttributeInterface $attribute,
        $property,
        $typeOfField,
        $strategy = null
    ): void {
        if ($attribute->getType()->isRelation() || $attribute->getType()->isEmbedded()) {
            $filterParameterNames = [
                $property,
                $property.'[]',
            ];

            foreach ($filterParameterNames as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $property,
                    'type' => 'string',
                    'required' => false,
                    'strategy' => BaseSearchFilter::STRATEGY_EXACT,
                ];
            }
        }

        $strategy = $strategy ?: BaseSearchFilter::STRATEGY_EXACT;
        $filterParameterNames = [$property];

        if (BaseSearchFilter::STRATEGY_EXACT === $strategy) {
            $filterParameterNames[] = $property.'[]';
        }

        foreach ($filterParameterNames as $filterParameterName) {
            $description[$filterParameterName] = [
                'property' => $property,
                'type' => $typeOfField,
                'required' => false,
                'strategy' => $strategy,
            ];
        }
    }

    /**
     * Converts an EAV type in PHP type.
     *
     * @param AttributeInterface $attribute
     *
     * @return string
     */
    protected function getType(AttributeInterface $attribute): string
    {
        switch ($attribute->getType()->getDatabaseType()) {
            case 'integerValue':
                return 'int';
            case 'boolValue':
                return 'bool';
            case 'dateValue':
            case 'datetimeValue':
                return \DateTimeInterface::class;
            case 'decimalValue':
                return 'float';
            case 'stringValue':
            case 'textValue':
                return 'string';
            case 'dataValue':
                return DataInterface::class;
        }

        return 'mixed';
    }

    /**
     * @param string $resourceClass
     * @param string $property
     *
     * @throws \UnexpectedValueException
     * @throws \LogicException
     * @throws MissingFamilyException
     * @throws MissingAttributeException
     *
     * @return AttributeInterface
     */
    protected function getAttribute(string $resourceClass, $property): AttributeInterface
    {
        $family = $this->getFamily($resourceClass);
        if (!$family->hasAttribute($property)) {
            if ('label' === $property) {
                return $family->getAttributeAsLabel();
            }
            if ('identifier' === $property) {
                return $family->getAttributeAsIdentifier();
            }
            // Special case for nested properties
            if (false !== strpos($property, '.')) {
                $attribute = null;
                foreach (explode('.', $property) as $attributeCode) {
                    if ($attribute instanceof AttributeInterface) { // If "parent" attribute resolved
                        $families = $attribute->getOption('allowed_families', []);
                        if (1 !== \count($families)) {
                            throw new \UnexpectedValueException(
                                "Bad 'allowed_families' configuration for attribute '{$attribute->getCode()}'"
                            );
                        }
                        $family = $this->familyRegistry->getFamily(reset($families));
                    }
                    $attribute = $family->getAttribute($attributeCode);
                }

                return $attribute;
            }
        }

        return $family->getAttribute($property);
    }

    /**
     * @param string $resourceClass
     *
     * @throws \LogicException
     * @throws MissingFamilyException
     * @throws \UnexpectedValueException
     *
     * @return FamilyInterface
     */
    protected function getFamily(string $resourceClass): FamilyInterface
    {
        if ($this->familyCode) {
            $family = $this->familyRegistry->getFamily($this->familyCode);
            if (ltrim($family->getDataClass(), '\\') !== ltrim($resourceClass, '\\')) {
                throw new \UnexpectedValueException("Resource class '{$resourceClass}' not matching family for filter");
            }

            return $family;
        }

        return $this->familyResolver->getFamily($resourceClass);
    }
}
