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
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Provides access to family registry through Api Platform.
 *
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
class UserProfileDataProvider implements ItemDataProviderInterface
{
    /** @var TokenStorageInterface */
    protected $tokenStorage;

    /**
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritDoc}
     *
     * @return UserInterface|null
     */
    public function getItem(
        string $resourceClass,
        $id,
        string $operationName = null,
        array $context = []
    ): ?UserInterface {
        if ('profile' !== $id || !is_a($resourceClass, UserInterface::class, true)) {
            throw new ResourceClassNotSupportedException("{$resourceClass} is not a valid resource class");
        }

        $token = $this->tokenStorage->getToken();

        /** @var UserInterface|null $user */
        $user = null;
        if ($token) {
            $user = $token->getUser();
        }

        return $user;
    }
}
