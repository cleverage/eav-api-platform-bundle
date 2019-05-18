<?php
/*
 * This file is part of the CleverAge/EAVApiPlatform package.
 *
 * Copyright (c) 2015-2019 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\EAVApiPlatformBundle\Serializer\Normalizer;

use ApiPlatform\Core\Exception\PropertyNotFoundException;
use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Serializer\ContextTrait;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException as SerializerInvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Removing any attributes from the Api Platform normalizer.
 *
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
class BaseApiNormalizer extends AbstractItemNormalizer
{
    use ContextTrait;

    /** @var SerializerInterface */
    protected $serializer;

    /** @var NormalizerInterface */
    protected $normalizer;

    /** @var DenormalizerInterface */
    protected $denormalizer;

    /**
     * @param NormalizerInterface $normalizer
     */
    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;
    }

    /**
     * @param DenormalizerInterface $denormalizer
     */
    public function setDenormalizer(DenormalizerInterface $denormalizer): void
    {
        $this->denormalizer = $denormalizer;
    }

    /**
     * @param SerializerInterface $serializer
     */
    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
        if ($this->normalizer instanceof SerializerAwareInterface) {
            $this->normalizer->setSerializer($serializer);
        }
        if ($this->denormalizer instanceof SerializerAwareInterface) {
            $this->denormalizer->setSerializer($serializer);
        }
        if ($this->normalizer instanceof NormalizerAwareInterface && $serializer instanceof NormalizerInterface) {
            $this->normalizer->setNormalizer($serializer);
        }
        if ($this->denormalizer instanceof DenormalizerAwareInterface && $serializer instanceof DenormalizerInterface) {
            $this->denormalizer->setDenormalizer($serializer);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException
     * @throws CircularReferenceException
     * @throws SerializerInvalidArgumentException
     * @throws LogicException
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $resourceClass = $this->resourceClassResolver->getResourceClass(
            $object,
            $context['resource_class'] ?? null,
            true
        );
        $context = $this->initContext($resourceClass, $context);
        $context['api_normalize'] = true;

        return $this->normalizer->normalize($object, $format, $context);
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException
     * @throws PropertyNotFoundException
     * @throws ResourceClassNotFoundException
     * @throws BadMethodCallException
     * @throws ExtraAttributesException
     * @throws SerializerInvalidArgumentException
     * @throws LogicException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        // Avoid issues with proxies if we populated the object
        if (isset($data['id']) && !isset($context['object_to_populate'])) {
            if (isset($context['api_allow_update']) && true !== $context['api_allow_update']) {
                throw new InvalidArgumentException('Update is not allowed for this operation.');
            }

            $this->updateObjectToPopulate($data, $context);
        }

        $context['api_denormalize'] = true;
        if (!isset($context['resource_class'])) {
            $context['resource_class'] = $class;
        }

        return $this->denormalizer->denormalize($data, $class, $format, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return
            $this->normalizer
            && $this->normalizer->supportsNormalization($data, $format)
            && parent::supportsNormalization($data, $format);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return
            $this->denormalizer
            && $this->denormalizer->supportsDenormalization($data, $type, $format)
            && parent::supportsDenormalization($data, $type, $format);
    }

    /**
     * @param array $data
     * @param array $context
     *
     * @throws InvalidArgumentException
     * @throws PropertyNotFoundException
     * @throws ResourceClassNotFoundException
     */
    protected function updateObjectToPopulate(array $data, array &$context): void
    {
        try {
            $context['object_to_populate'] = $this->iriConverter->getItemFromIri(
                (string) $data['id'],
                $context + ['fetch_data' => false]
            );
        } catch (InvalidArgumentException $e) {
            $identifier = null;
            $properties = $this->propertyNameCollectionFactory->create($context['resource_class'], $context);
            foreach ($properties as $propertyName) {
                if ($this->propertyMetadataFactory->create($context['resource_class'], $propertyName)->isIdentifier()) {
                    $identifier = $propertyName;
                    break;
                }
            }

            if (null === $identifier) {
                throw $e;
            }

            $context['object_to_populate'] = $this->iriConverter->getItemFromIri(
                sprintf(
                    '%s/%s',
                    $this->iriConverter->getIriFromResourceClass($context['resource_class']),
                    $data[$identifier]
                ),
                $context + ['fetch_data' => false]
            );
        }
    }
}
