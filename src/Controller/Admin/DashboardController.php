<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
// Дублює access_control (^/admin) у security.yaml навмисно: defense in depth, якщо правило зміниться.
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Car Expenses');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Головна', 'fa fa-home');
        yield MenuItem::linkTo(CarCrudController::class, 'Машини', 'fa fa-car');
        yield MenuItem::linkTo(ExpenseCrudController::class, 'Витрати', 'fa fa-money-bill');
        yield MenuItem::linkToRoute('Сайт', 'fa fa-globe', 'car_index');
    }
}
