<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Car;
use App\Repository\CarRepository;
use App\Repository\Contract\CarWithExpensesFinder;
use App\Service\CarExpenseStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CarController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('car_index');
    }

    #[Route('/cars', name: 'car_index', methods: ['GET'])]
    public function index(CarRepository $cars, CarExpenseStats $stats): Response
    {
        $rows = [];
        foreach ($cars->findAllWithExpenses() as $car) {
            $rows[] = [
                'car' => $car,
                'expensesCount' => $car->getExpenses()->count(),
                'total' => $stats->total($car),
            ];
        }

        return $this->render('car/index.html.twig', ['rows' => $rows]);
    }

    #[Route('/cars/{slug}', name: 'car_show', requirements: ['slug' => Car::SLUG_PATTERN], methods: ['GET'])]
    public function show(string $slug, CarWithExpensesFinder $cars, CarExpenseStats $stats): Response
    {
        $car = $cars->findOneBySlugWithExpenses($slug) ?? throw $this->createNotFoundException('Машину не знайдено');

        return $this->render('car/show.html.twig', [
            'car' => $car,
            'total' => $stats->total($car),
            'byCategory' => $stats->totalsByCategory($car),
        ]);
    }
}
