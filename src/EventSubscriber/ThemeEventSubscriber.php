<?php

namespace App\EventSubscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ThemeEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['handleThemeOperations', EventPriorities::PRE_WRITE],
        ];
    }

    public function handleThemeOperations(ViewEvent $event): void
    {
        $theme = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();

        if (!$theme instanceof Theme) {
            return;
        }

        if ($method === Request::METHOD_POST) {
            $this->handleNewTheme($theme);
        } elseif ($method === Request::METHOD_PUT || $method === Request::METHOD_PATCH) {
            $this->handleUpdateTheme($theme);
        }
    }

    private function handleNewTheme(Theme $newTheme): void
    {
        $defaultExists = $this->em->getRepository(Theme::class)->count(['isDefault' => true]);

        if ($newTheme->getIsDefault()) {
            $this->disableOtherDefaults();
        } elseif (!$defaultExists) {
            $newTheme->setIsDefault(true);
        }
    }

    private function handleUpdateTheme(Theme $updatedTheme): void
    {
        $originalTheme = $this->em->getUnitOfWork()->getOriginalEntityData($updatedTheme);

        if ($originalTheme['isDefault'] && !$updatedTheme->getIsDefault()) {
            throw new BadRequestHttpException('Cannot unset default theme. Set another theme as default first.');
        }

        if ($updatedTheme->getIsDefault()) {
            $this->disableOtherDefaults($updatedTheme);
        }
    }

    private function disableOtherDefaults(?Theme $currentTheme = null): void
    {
        $criteria = ['isDefault' => true];
        if ($currentTheme !== null) {
            $criteria['id'] = ['neq' => $currentTheme->getId()];
        }

        $defaultThemes = $this->em->getRepository(Theme::class)->findBy($criteria);
        foreach ($defaultThemes as $theme) {
            $theme->setIsDefault(false);
            $this->em->persist($theme);
        }
    }
}