<?php

namespace App\Controller\Admin;

use App\Entity\Archivo;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use function Symfony\Component\String\u;

class ArchivoCrudController extends AbstractCrudController
{

    public function __construct(private Filesystem $filesystem, private EntityManagerInterface $entityManagerInterface)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Archivo::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [

            ImageField::new('path')
                ->setUploadDir('public/images')
                ->setUploadedFileNamePattern('[slug].[extension]'),
            IdField::new('id')->hideOnForm(),
            $pageName == Crud::PAGE_EDIT && $this->getContext()->getEntity()->getInstance()->isNoEditar() ? TextField::new('nombre')->hideWhenUpdating() : TextField::new('nombre'),
            TextField::new('slug')->onlyOnIndex(),
            TextField::new('extencion', 'Extención')->onlyOnIndex(),
            BooleanField::new('webP', 'Crear versión webp')->onlyWhenCreating(),
            BooleanField::new('no_editar', 'No editar')->hideWhenUpdating(),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            // the labels used to refer to this entity in titles, buttons, etc.
            ->setEntityLabelInSingular('Imagen')
            ->setEntityLabelInPlural('Imágenes');
    }

    protected function processUploadedFiles(FormInterface $form): void
    {
        /** @var FormInterface $child */
        foreach ($form as $child) {
            $config = $child->getConfig();

            if (!$config->getType()->getInnerType() instanceof FileUploadType) {
                if ($config->getCompound()) {
                    $this->processUploadedFiles($child);
                }

                continue;
            }

            /** @var FileUploadState $state */
            $state = $config->getAttribute('state');

            if (!$state->isModified()) {
                continue;
            }

            $uploadDelete = $config->getOption('upload_delete');

            if ($state->hasCurrentFiles() && ($state->isDelete() || (!$state->isAddAllowed() && $state->hasUploadedFiles()))) {
                foreach ($state->getCurrentFiles() as $file) {
                    $uploadDelete($file);
                }
                $state->setCurrentFiles([]);
            }

            $filePaths = (array) $child->getData();
            $uploadDir = $config->getOption('upload_dir');
            $uploadNew = $config->getOption('upload_new');


            $entity = $form->getData();
            foreach ($state->getUploadedFiles() as $index => $file) {

                if ($entity->getId()) {
                    $oldEntity = $this->entityManagerInterface->getUnitOfWork()->getOriginalEntityData($entity);
                    if ($oldEntity['path'] && $this->filesystem->exists('images/' . $oldEntity['path'])) {
                        $this->filesystem->remove('images/' . $oldEntity['path']);
                    }
                } else {
                    $entity->setExtencion($file->getClientOriginalExtension())->setTipo(Archivo::IMAGEN);
                    if (!$entity->getNombre()) {
                        $entity->setNombre($file->getClientOriginalName());
                    }
                }
                $fileName = u($filePaths[$index])->replace($uploadDir, '')->toString();
                $uploadNew($file, $uploadDir, $fileName);
            }
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getFileName() && $this->filesystem->exists($entityInstance->getPath())) {
            $this->filesystem->remove($entityInstance->getPath());
        }
        $entityManager->remove($entityInstance);
        $entityManager->flush();
    }
}
