<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\Field\PlainDateField;
use App\Entity\Expense;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<Expense>
 */
#[IsGranted('ROLE_ADMIN')]
final class ExpenseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Expense::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Витрата')
            ->setEntityLabelInPlural('Витрати')
            ->setDefaultSort(['date' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields(['category', 'description', 'car.name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('car', 'Машина');
        // Сума — рядок decimal(10,2): TextField не проганяє її через float/округлення,
        // а формат, знак і межі перевіряє сутність (Assert\Regex/Positive/LessThanOrEqual).
        yield TextField::new('amount', 'Сума')
            ->setHelp('Не більше 2 знаків після коми, до 1 000 000.')
            ->setFormTypeOption('attr', ['inputmode' => 'decimal']);
        yield TextField::new('category', 'Категорія');
        yield PlainDateField::new('date', 'Дата');
        yield TextareaField::new('description', 'Опис')->hideOnIndex();
    }
}
