<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CarDto;
use App\Dto\ExpenseDto;
use App\Entity\Car;
use App\Repository\CarRepository;
use App\Repository\Contract\CarSlugExistence;
use App\Repository\Contract\ExpensesByCarSlugFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CarApiController extends AbstractController
{
    #[Route('/api/cars', name: 'api_cars_list', methods: ['GET'])]
    public function list(CarRepository $cars): JsonResponse
    {
        return $this->json(array_map(CarDto::fromEntity(...), $cars->findAllOrderedByName()));
    }

    #[Route('/api/cars/{slug}/expenses', name: 'api_cars_expenses', methods: ['GET'])]
    public function expenses(string $slug, CarSlugExistence $cars, ExpensesByCarSlugFinder $expenses): JsonResponse
    {
        if (!Car::isValidSlug($slug) || !$cars->existsBySlug($slug)) {
            return new JsonResponse(['error' => 'Машину не знайдено'], 404);
        }

        return $this->json(array_map(ExpenseDto::fromEntity(...), $expenses->findByCarSlug($slug)));
    }
}
