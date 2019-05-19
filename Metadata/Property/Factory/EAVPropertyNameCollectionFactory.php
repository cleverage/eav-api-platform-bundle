<?php
/*
 * This file is part of the CleverAge/EAVApiPlatform package.
 *
 * Copyright (c) 2015-2019 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\EAVApiPlatformBundle\Metadata\Property\Factory;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyNameCollection;
use CleverAge\EAVApiPlatformBundle\Resolver\FamilyResolver;
use Sidus\EAVModelBundle\Entity\DataInterface;

/**
 * Overriding property name collection factory for EAV data to remove "values" and inject EAV attributes.
 *
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
class EAVPropertyNameCollectionFactory implements PropertyNameCollectionFactoryInterface
{
    /** @var PropertyNameCollectionFactoryInterface */
    protected $propertyNameCollectionFactory;

    /** @var FamilyResolver */
    protected $familyResolver;

    /** @var array */
    protected $ignoredAttributes;

    /**
     * @param PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory
     * @param FamilyResolver                         $familyResolver
     * @param array                                  $ignoredAttributes
     */
    public function __construct(
        PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        FamilyResolver $familyResolver,
        array $ignoredAttributes
    ) {
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->familyResolver = $familyResolver;
        $this->ignoredAttributes = $ignoredAttributes;
    }

    /**
     * Creates the property name collection for the given class and options.
     *
     * @param string $resourceClass
     * @param array  $options
     *
     * @throws ResourceClassNotFoundException
     *
     * @return PropertyNameCollection
     */
    public function create(string $resourceClass, array $options = []): PropertyNameCollection
    {
        if (is_a($resourceClass, DataInterface::class, true)) {
            $family = $this->familyResolver->getFamily($resourceClass);
            $options['family'] = $family->getCode();
        }

        $propertyNameCollection = $this->propertyNameCollectionFactory->create($resourceClass, $options);
        if (is_a($resourceClass, DataInterface::class, true)) {
            $resolvedProperties = [];
            foreach ($propertyNameCollection as $propertyName) {
                if (!\in_array($propertyName, $this->ignoredAttributes, true)) {
                    $resolvedProperties[] = $propertyName;
                }
            }
            $propertyNameCollection = new PropertyNameCollection($resolvedProperties);
        }

        return $propertyNameCollection;
    }
}
