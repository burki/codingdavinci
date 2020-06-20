<?php

/*
 * This file is part of the Pagerfanta package.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Searchwidget\View;

// use Pagerfanta\Exception\InvalidArgumentException;

/**
 * ViewFactory.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class ViewFactory /* implements ViewFactoryInterface */
{
    private $views;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->views = [];
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, DefaultView $view) // todo: changeback to ViewInterface
    {
        $this->views[$name] = $view;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return isset($this->views[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function add(array $views)
    {
        foreach ($views as $name => $view) {
            $this->set($name, $view);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The view "%s" does not exist.', $name));
        }

        return $this->views[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function remove($name)
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The view "%s" does not exist.', $name));
        }

        unset($this->views[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->views;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->views = [];
    }
}
