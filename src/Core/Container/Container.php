<?php

declare(strict_types=1);

namespace Nikanzo\Core\Container;

use Nikanzo\Core\Attributes\Inject;
use Nikanzo\Core\Attributes\Service;
use Nikanzo\Core\Attributes\Singleton;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

final class Container implements ContainerInterface
{
    private ContainerBuilder $builder;
    private bool $compiled = false;

    public function __construct()
    {
        $this->builder = new ContainerBuilder();
    }

    public function register(string $id): void
    {
        $config = $this->resolveServiceConfig($id);
        $definition = new Definition($id);
        $definition->setAutowired(true);
        $definition->setPublic($config['public']);
        $definition->setShared($config['shared']);
        $definition->setLazy($config['lazy']);

        $this->builder->setDefinition($id, $definition);
        $this->compiled = false;
    }

    public function get(string $id): mixed
    {
        if (!$this->builder->has($id)) {
            $this->register($id);
        }

        if (!$this->compiled) {
            $this->builder->compile(true);
            $this->compiled = true;
        }

        try {
            $service = $this->builder->get($id);
        } catch (\Throwable $e) {
            if ($e instanceof ServiceNotFoundException) {
                throw new \RuntimeException(sprintf('Service %s not found', $id));
            }
            throw $e;
        }

        $this->applyPropertyInjection($service);

        return $service;
    }

    public function has(string $id): bool
    {
        return $this->builder->has($id);
    }

    /**
     * @param array<string, mixed> $providedArgs
     */
    public function call(object $instance, string $method, array $providedArgs = []): mixed
    {
        $refMethod = new ReflectionMethod($instance, $method);
        $args = [];

        foreach ($refMethod->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $providedArgs)) {
                $args[] = $providedArgs[$name];
                continue;
            }

            $injectAttributes = $parameter->getAttributes(Inject::class);
            if ($injectAttributes !== []) {
                $inject = $injectAttributes[0]->newInstance();
                $serviceId = $inject->serviceId;

                if ($serviceId === null) {
                    $type = $parameter->getType();
                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $serviceId = $type->getName();
                    }
                }

                if ($serviceId !== null) {
                    $args[] = $this->get($serviceId);
                    continue;
                }
            }

            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
                continue;
            }

            $args[] = $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : null;
        }

        return $refMethod->invokeArgs($instance, $args);
    }

    private function applyPropertyInjection(object $service): void
    {
        $refClass = new ReflectionClass($service);
        foreach ($refClass->getProperties() as $property) {
            foreach ($property->getAttributes(Inject::class) as $attribute) {
                $targetId = $attribute->newInstance()->serviceId;
                if ($targetId === null) {
                    $type = $property->getType();
                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $targetId = $type->getName();
                    }
                }

                if ($targetId !== null) {
                    $property->setAccessible(true);
                    $property->setValue($service, $this->get($targetId));
                }
            }
        }
    }

    private function isSingleton(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        $ref = new ReflectionClass($class);

        return (bool) $ref->getAttributes(Singleton::class);
    }

    /**
     * @return array{lazy: bool, public: bool, shared: bool}
     */
    private function resolveServiceConfig(string $class): array
    {
        $config = [
            'lazy' => false,
            'public' => true,
            'shared' => true,
        ];

        if (!class_exists($class)) {
            return $config;
        }

        $ref = new ReflectionClass($class);
        $serviceAttrs = $ref->getAttributes(Service::class);

        if ($serviceAttrs !== []) {
            $service = $serviceAttrs[0]->newInstance();
            if ($service->lazy !== null) {
                $config['lazy'] = $service->lazy;
            }
            if ($service->public !== null) {
                $config['public'] = $service->public;
            }
            if ($service->shared !== null) {
                $config['shared'] = $service->shared;
            }
        }

        if ($serviceAttrs === [] && $this->isSingleton($class)) {
            $config['shared'] = true;
        } elseif ($serviceAttrs !== [] && $serviceAttrs[0]->newInstance()->shared === null && $this->isSingleton($class)) {
            // If shared not set on Service, fall back to Singleton attribute
            $config['shared'] = true;
        }

        return $config;
    }
}
