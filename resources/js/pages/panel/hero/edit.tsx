import { Form, Head, Link } from '@inertiajs/react';
import HeroSlideController from '@/actions/App/Http/Controllers/Panel/HeroSlideController';
import Heading from '@/components/heading';
import HeroSlideFormFields from '@/components/hero-slide-form-fields';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/panel/hero';
import type { ManagedHeroSlide } from '@/types';

/** Edición de un slide. El fondo sólo se reemplaza si se elige uno nuevo. */
export default function EditHeroSlide({ slide }: { slide: ManagedHeroSlide }) {
    return (
        <>
            <Head title="Editar slide" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Editar slide"
                    description="Los cambios se ven en la portada apenas se guardan."
                />

                <Form {...HeroSlideController.update.form(slide.id)}>
                    {({ processing, errors }) => (
                        <HeroSlideFormFields
                            errors={errors}
                            slide={slide}
                            acciones={
                                <div className="flex gap-2">
                                    <Button
                                        disabled={processing}
                                        data-test="save-hero-button"
                                    >
                                        Guardar
                                    </Button>

                                    <Button asChild variant="secondary">
                                        <Link href={index()}>Cancelar</Link>
                                    </Button>
                                </div>
                            }
                        />
                    )}
                </Form>
            </div>
        </>
    );
}

EditHeroSlide.layout = {
    breadcrumbs: [{ title: 'Portada', href: index() }],
};
