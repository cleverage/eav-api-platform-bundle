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

use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\Exception\PropertyNotFoundException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use ApiPlatform\Core\Metadata\Property\SubresourceMetadata;
use Sidus\EAVModelBundle\Entity\DataInterface;
use Sidus\EAVModelBundle\Model\AttributeInterface;
use Sidus\EAVModelBundle\Registry\FamilyRegistry;
use Sidus\EAVModelBundle\Serializer\MaxDepthHandler;
use Sidus\EAVModelBundle\Serializer\Normalizer\EAVDataNormalizer;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use UnexpectedValueException;

/**
 * Returns the proper property metadata for EAV attributes
 *
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
class EAVAttributeMetadataFactory implements PropertyMetadataFactoryInterface
{
    /** @var PropertyMetadataFactoryInterface */
    protected $propertyMetadataFactory;

    /** @var PropertyInfoExtractorInterface */
    protected $propertyInfoExtractor;

    /** @var FamilyRegistry */
    protected $familyRegistry;

    /** @var ResourceClassResolverInterface */
    protected $resourceClassResolver;

    /**
     * @param PropertyMetadataFactoryInterface $propertyMetadataFactory
     * @param PropertyInfoExtractorInterface   $propertyInfoExtractor
     * @param FamilyRegistry                   $familyRegistry
     * @param ResourceClassResolverInterface   $resourceClassResolver
     */
    public function __construct(
        PropertyMetadataFactoryInterface $propertyMetadataFactory,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        FamilyRegistry $familyRegistry,
        ResourceClassResolverInterface $resourceClassResolver
    ) {
        $this->propertyMetadataFactory = $propertyMetadataFactory;
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->familyRegistry = $familyRegistry;
        $this->resourceClassResolver = $resourceClassResolver;
    }

    /**
     * {@inheritDoc}
     */
    public function create(string $resourceClass, string $property, array $options = []): PropertyMetadata
    {
        if (!is_a($resourceClass, DataInterface::class, true)) {
            return $this->propertyMetadataFactory->create($resourceClass, $property, $options);
        }
        $family = $this->familyRegistry->getFamilyByDataClass($resourceClass);

        if (!$family->hasAttribute($property)) {
            $metadata = $this->propertyMetadataFactory->create($resourceClass, $property, $options);
            if ($family->getAttributeAsIdentifier()) {
                return $metadata->withIdentifier(false); // Remove Id as identifier
            }

            return $metadata;
        }

        $attribute = $family->getAttribute($property);
        $types = $this->propertyInfoExtractor->getTypes($resourceClass, $property);
        if (!is_array($types)) {
            throw new UnexpectedValueException('Extracted types are not an array');
        }
        $type = reset($types); // @todo We have a major problem here, how to we resolve multiple types?
        if (!$type instanceof Type) {
            throw new PropertyNotFoundException("Unable to resolve type for attribute {$attribute->getCode()}");
        }

        $subResourceMetadata = $this->resovleSubResourceMetadata($attribute, $type);

        $identifier = $family->getAttributeAsIdentifier();
        $isIdentifier = false;
        if ($identifier && $identifier->getCode() === $attribute->getCode()) {
            $isIdentifier = true;
        }

        return new PropertyMetadata(
            $type,
            $attribute->getOption('description'),
            true,
            true,
            true,
            true,
            $attribute->isRequired(),
            $isIdentifier,
            null,
            null,
            null,
            $subResourceMetadata
        );
    }

    /**
     * @param AttributeInterface $attribute
     * @param Type               $type
     *
     * @return SubresourceMetadata|null
     */
    protected function resovleSubResourceMetadata(AttributeInterface $attribute, Type $type): ?SubresourceMetadata
    {
        if (!$attribute->getType()->isRelation() && !$attribute->getType()->isEmbedded()) {
            return null;
        }
        $dataClass = $type->getClassName();
        if ($attribute->isCollection()) {
            $collectionType = $type->getCollectionValueType();
            if ($collectionType) { // If not, WTF?
                $dataClass = $collectionType->getClassName();
            }
        }
        if (!$this->resourceClassResolver->isResourceClass($dataClass)) {
            return null;
        }
        $options = $attribute->getOption(EAVDataNormalizer::SERIALIZER_OPTIONS, []);
        $maxDepth = null;
        if (array_key_exists(MaxDepthHandler::MAX_DEPTH_KEY, $options)) {
            $maxDepth = $options[MaxDepthHandler::MAX_DEPTH_KEY];
        }

        return new SubresourceMetadata(
            $dataClass,
            $attribute->isCollection(),
            (int) $maxDepth
        );
    }
}
