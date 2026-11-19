<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Car;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<Car>
 */
#[IsGranted('ROLE_ADMIN')]
final class CarCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Car::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Машина')
            ->setEntityLabelInPlural('Машини')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['slug', 'name', 'country']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        // Формат slug перевіряє сутність (Car::SLUG_PATTERN), а не поле форми.
        yield TextField::new('slug', 'Slug')->setHelp('Малі латинські літери a-z, цифри та дефіс.');
        yield TextField::new('name', 'Назва');
        yield TextField::new('country', 'Країна');
    }
}
