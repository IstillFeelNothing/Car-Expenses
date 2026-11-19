<?php

declare(strict_types=1);

namespace App\Controller\Admin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Поле дати для date_immutable без PHP-розширення intl.
 *
 * Вбудований DateField форматує значення через Intl і кидає LogicException, якщо розширення
 * не ввімкнено. Це поле — нативний <input type="date"> і показ у форматі Y-m-d.
 * Власний клас (а не Field::new()) потрібен, бо EasyAdmin підміняє загальний Field
 * на DateField за типом колонки Doctrine.
 */
final class PlainDateField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('admin/field/plain_date.html.twig')
            ->setFormType(DateType::class)
            ->setFormTypeOptions(['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->addCssClass('field-date')
            ->setDefaultColumns('col-md-6 col-xxl-5')
            ->formatValue(static fn (mixed $value): string => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : '');
    }
}
