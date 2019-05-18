<?php
/*
 * This file is part of the CleverAge/EAVApiPlatform package.
 *
 * Copyright (c) 2015-2019 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\EAVApiPlatformBundle\EAV\Filter;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use Sidus\EAVModelBundle\Doctrine\AttributeQueryBuilderInterface;
use Sidus\EAVModelBundle\Doctrine\DQLHandlerInterface;
use Sidus\EAVModelBundle\Doctrine\EAVQueryBuilderInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter as BaseSearchFilter;

/**
 * Filter the collection by given properties.
 *
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
class SearchFilter extends AbstractEAVFilter
{
    /**
     * {@inheritDoc}
     *
     * @throws \ApiPlatform\Core\Exception\InvalidArgumentException
     */
    protected function filterAttribute(
        EAVQueryBuilderInterface $eavQb,
        AttributeQueryBuilderInterface $attributeQueryBuilder,
        $value,
        $strategy = null,
        string $operationName = null
    ): ?DQLHandlerInterface {
        switch ($strategy) {
            case null:
            case BaseSearchFilter::STRATEGY_EXACT:
                $value = trim($value, '%');
                break;
            case BaseSearchFilter::STRATEGY_PARTIAL:
                $value = '%'.trim($value, '%').'%';
                break;
            case BaseSearchFilter::STRATEGY_START:
                $value = trim($value, '%').'%';
                break;
            case BaseSearchFilter::STRATEGY_END:
                $value = '%'.trim($value, '%');
                break;
            case BaseSearchFilter::STRATEGY_WORD_START:
                $value = '%'.trim($value, '%').'%'; // No, we are not going to implement this
                break;
            default:
                throw new InvalidArgumentException("strategy {$strategy} does not exist.");
        }

        return $attributeQueryBuilder->like($value);
    }
}
