<?php
/*
 * This file is part of the CleverAge/EAVApiPlatform package.
 *
 * Copyright (c) 2015-2019 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\EAVApiPlatformBundle\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Sidus\EAVModelBundle\Doctrine\EAVFinder;
use Sidus\EAVModelBundle\Entity\DataInterface;
use Sidus\EAVModelBundle\Registry\FamilyRegistry;

/**
 * Provide access to EAV data through Doctrine id or identifier attribute
 */
class EAVDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    /** @var FamilyRegistry */
    protected $familyRegistry;

    /** @var EAVFinder */
    protected $eavFinder;

    /**
     * @param FamilyRegistry $familyRegistry
     * @param EAVFinder      $eavFinder
     */
    public function __construct(FamilyRegistry $familyRegistry, EAVFinder $eavFinder)
    {
        $this->familyRegistry = $familyRegistry;
        $this->eavFinder = $eavFinder;
    }

    /**
     * {@inheritDoc}
     */
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = [])
    {
        $family = $this->familyRegistry->getFamilyByDataClass($resourceClass);

        return $this->eavFinder->findByIdentifier($family, $id, true);
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return is_a($resourceClass, DataInterface::class, true);
    }
}
