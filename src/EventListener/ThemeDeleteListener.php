<?php

namespace App\EventListener;

use App\Entity\Theme;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ThemeDeleteListener
{
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Theme && $entity->getIsDefault()) {
            throw new BadRequestHttpException('Cannot delete the default theme.');
        }
    }
}