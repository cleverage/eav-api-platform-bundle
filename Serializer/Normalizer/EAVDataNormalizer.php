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

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\RuntimeException;
use function get_class;
use Sidus\EAVModelBundle\Entity\DataInterface;
use Sidus\EAVModelBundle\Exception\ContextException;
use Sidus\EAVModelBundle\Exception\EAVExceptionInterface;
use Sidus\EAVModelBundle\Exception\InvalidValueDataException;
use Sidus\EAVModelBundle\Exception\MissingAttributeException;
use Sidus\EAVModelBundle\Model\AttributeInterface;
use Sidus\EAVModelBundle\Serializer\Normalizer\EAVDataNormalizer as BaseEAVDataNormalizer;
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\RuntimeException as SerializerRuntimeException;

/**
 * Overriding relation handling.
 *
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
class EAVDataNormalizer extends BaseEAVDataNormalizer
{
    /** @var IriConverterInterface */
    protected $iriConverter;

    /**
     * @param IriConverterInterface $iriConverter
     */
    public function setIriConverter(IriConverterInterface $iriConverter): void
    {
        $this->iriConverter = $iriConverter;
    }

    /**
     * Normalizes an object into a set of arrays/scalars.
     *
     * @param DataInterface $object  object to normalize
     * @param string        $format  format the normalization result will be encoded as
     * @param array         $context Context options for the normalizer
     *
     * @throws InvalidArgumentException
     * @throws SerializerRuntimeException
     * @throws ExceptionInterface
     * @throws EAVExceptionInterface
     * @throws InvalidValueDataException
     * @throws CircularReferenceException
     * @throws RuntimeException
     * @throws \ApiPlatform\Core\Exception\InvalidArgumentException
     * @throws \ReflectionException
     *
     * @return array|string|null
     */
    public function normalize($object, $format = null, array $context = [])
    {
        // Do the same for 'by_reference' ?
        if ($this->iriConverter
            && $this->byReferenceHandler->isByShortReference($context)
        ) {
            return $this->iriConverter->getIriFromItem($object);
        }

        return parent::normalize($object, $format, $context);
    }

    /**
     * We must override this method because we cannot expect the normalizer to work normally with collection with
     * the API Platform framework.
     *
     * @param DataInterface $object
     * @param string        $attribute
     * @param string        $format
     * @param array         $context
     *
     * @throws ExceptionInterface
     * @throws LogicException
     * @throws InvalidArgumentException
     * @throws CircularReferenceException
     *
     * @return mixed
     */
    protected function getAttributeValue(
        DataInterface $object,
        $attribute,
        $format = null,
        array $context = []
    ) {
        $rawValue = $this->propertyAccessor->getValue($object, $attribute);
        if (!\is_array($rawValue) && !$rawValue instanceof \Traversable) {
            $subContext = $this->getAttributeContext($object, $attribute, $rawValue, $context);

            return $this->normalizer->normalize($rawValue, $format, $subContext);
        }

        $collection = [];
        /** @var array $rawValue */
        foreach ($rawValue as $item) {
            $subContext = $this->getAttributeContext($object, $attribute, $item, $context);
            $collection[] = $this->normalizer->normalize($item, $format, $subContext);
        }

        return $collection;
    }

    /**
     * We must override this method because we cannot expect the normalizer to work normally with collection with
     * the API Platform framework.
     *
     * @param DataInterface      $object
     * @param AttributeInterface $attribute
     * @param string             $format
     * @param array              $context
     *
     * @throws EAVExceptionInterface
     * @throws MissingAttributeException
     * @throws InvalidValueDataException
     * @throws ContextException
     * @throws LogicException
     * @throws InvalidArgumentException
     * @throws CircularReferenceException
     *
     * @return mixed
     */
    protected function getEAVAttributeValue(
        DataInterface $object,
        AttributeInterface $attribute,
        $format = null,
        array $context = []
    ) {
        $rawValue = $object->get($attribute->getCode());
        if (!\is_array($rawValue) && !$rawValue instanceof \Traversable) {
            $subContext = $this->getEAVAttributeContext($object, $attribute, $rawValue, $context);

            return $this->normalizer->normalize($rawValue, $format, $subContext);
        }

        $collection = [];
        /** @var array $rawValue */
        foreach ($rawValue as $item) {
            $subContext = $this->getEAVAttributeContext($object, $attribute, $item, $context);
            $collection[] = $this->normalizer->normalize($item, $format, $subContext);
        }

        return $collection;
    }

    /**
     * @param DataInterface $object
     * @param string        $attribute
     * @param mixed         $rawValue
     * @param array         $context
     *
     * @return array
     */
    protected function getAttributeContext(
        DataInterface $object,
        $attribute,
        /* @noinspection PhpUnusedParameterInspection */
        $rawValue,
        array $context
    ): array {
        $resolvedContext = parent::getAttributeContext($object, $attribute, $rawValue, $context);

        if (!\is_object($rawValue)) {
            return $resolvedContext;
        }

        $resolvedContext['resource_class'] = get_class($rawValue);
        unset($resolvedContext['item_operation_name'], $resolvedContext['collection_operation_name']);

        return $resolvedContext;
    }

    /**
     * @param DataInterface      $object
     * @param AttributeInterface $attribute
     * @param mixed              $rawValue
     * @param array              $context
     *
     * @return array
     */
    protected function getEAVAttributeContext(
        DataInterface $object,
        AttributeInterface $attribute,
        /* @noinspection PhpUnusedParameterInspection */
        $rawValue,
        array $context
    ): array {
        $resolvedContext = parent::getEAVAttributeContext($object, $attribute, $rawValue, $context);

        if (!\is_object($rawValue)) {
            return $resolvedContext;
        }

        $resolvedContext['resource_class'] = get_class($rawValue);
        unset($resolvedContext['item_operation_name'], $resolvedContext['collection_operation_name']);

        return $resolvedContext;
    }
}
