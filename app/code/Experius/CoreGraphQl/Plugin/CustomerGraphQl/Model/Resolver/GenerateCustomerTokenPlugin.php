<?php
/**
 * Copyright © Experius. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\CoreGraphQl\Plugin\CustomerGraphQl\Model\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AuthenticationInterface;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\InvalidEmailOrPasswordException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\UserLockedException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\CustomerGraphQl\Model\Resolver\GenerateCustomerToken;
use Magento\Framework\Exception\AuthenticationException;

/**
 * Plugin to enhance customer login error handling in GraphQL
 *
 * Provides specific error messages for different authentication failure scenarios:
 * - Invalid email address
 * - Locked/disabled accounts
 * - Incorrect credentials
 * - Email not confirmed
 */
class GenerateCustomerTokenPlugin
{
    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var AuthenticationInterface
     */
    private AuthenticationInterface $authentication;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param AuthenticationInterface $authentication
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AuthenticationInterface $authentication
    ) {
        $this->customerRepository = $customerRepository;
        $this->authentication = $authentication;
    }

    /**
     * Around plugin to intercept resolve method and enhance error handling
     *
     * @param GenerateCustomerToken $subject
     * @param callable $proceed
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param mixed $context
     * @param \Magento\Framework\GraphQl\Schema\Type\ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlAuthenticationException
     */
    public function aroundResolve(
        GenerateCustomerToken $subject,
        callable $proceed,
        $field,
        $context,
        $info,
        array $value = null,
        array $args = null
    ) {
        try {
            return $proceed($field, $context, $info, $value, $args);
        } catch (GraphQlAuthenticationException $e) {
            $this->handleAuthenticationException($e, $args);
            // If handleAuthenticationException didn't throw, re-throw original
            throw $e;
        } catch (\Exception $e) {
            $this->handleAuthenticationException($e, $args);
            // If handleAuthenticationException didn't throw, re-throw original
            throw $e;
        }
    }

    /**
     * Handle authentication exceptions and provide specific error messages
     *
     * @param \Exception $exception
     * @param array|null $args
     * @return void
     * @throws GraphQlAuthenticationException
     */
    private function handleAuthenticationException(\Exception $exception, ?array $args): void
    {
        $email = $args['email'] ?? '';
        
        // Handle EmailNotConfirmedException
        if ($exception instanceof EmailNotConfirmedException) {
            throw new GraphQlAuthenticationException(
                __('This account isn\'t confirmed. Verify and try again.'),
                $exception
            );
        }
        
        // Handle UserLockedException
        if ($exception instanceof UserLockedException) {
            throw new GraphQlAuthenticationException(
                __('The account is locked. Please contact customer support.'),
                $exception
            );
        }
        
        // Handle InvalidEmailOrPasswordException or AuthenticationException
        $originalException = $exception;
        if ($exception instanceof GraphQlAuthenticationException) {
            $originalException = $exception->getPrevious();
        }
        
        if ($originalException instanceof AuthenticationException) {
            // AuthenticationException wraps other exceptions, check the previous exception
            $wrappedException = $originalException->getPrevious();
            if ($wrappedException instanceof UserLockedException) {
                throw new GraphQlAuthenticationException(
                    __('The account is locked. Please contact customer support.'),
                    $wrappedException
                );
            }
            if ($wrappedException instanceof EmailNotConfirmedException) {
                throw new GraphQlAuthenticationException(
                    __('This account isn\'t confirmed. Verify and try again.'),
                    $wrappedException
                );
            }
        }
        
        // For InvalidEmailOrPasswordException or generic authentication failures,
        // check if customer exists to determine specific error message
        if ($originalException instanceof InvalidEmailOrPasswordException 
            || ($originalException instanceof AuthenticationException && $email)
        ) {
            if ($email) {
                try {
                    $customer = $this->customerRepository->get($email);
                    $customerId = $customer->getId();
                    
                    // Check if account is locked
                    if ($this->authentication->isLocked($customerId)) {
                        throw new GraphQlAuthenticationException(
                            __('The account is locked. Please contact customer support.'),
                            new UserLockedException(__('The account is locked.'))
                        );
                    }
                    
                    // Customer exists, so it's wrong password
                    throw new GraphQlAuthenticationException(
                        __('The password you entered is incorrect. Please try again.'),
                        $originalException instanceof InvalidEmailOrPasswordException 
                            ? $originalException 
                            : new InvalidEmailOrPasswordException(__('Invalid login or password.'))
                    );
                } catch (NoSuchEntityException $noEntityException) {
                    // Customer doesn't exist - invalid email
                    throw new GraphQlAuthenticationException(
                        __('The email address you entered is not registered. Please check and try again.'),
                        new InvalidEmailOrPasswordException(__('Invalid login or password.'))
                    );
                }
            }
        }
    }
}
