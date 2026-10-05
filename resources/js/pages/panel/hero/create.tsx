import { Form, Head, Link } from '@inertiajs/react';
import HeroSlideController from '@/actions/App/Http/Controllers/Panel/HeroSlideController';
import Heading from '@/components/heading';
import HeroSlideFormFields from '@/components/hero-slide-form-fields';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/panel/hero';

/** Alta de un slide del hero de la portada. */
export default function CreateHeroSlide() {
    return (
        <>
            <Head title="Nuevo slide" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Nuevo slide"
                    description="Sale en la portada apenas quede visible y dentro de sus fechas."
                />

                <Form {...HeroSlideController.store.form()}>
                    {({ processing, errors }) => (
                        <HeroSlideFormFields
                            errors={errors}
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

CreateHeroSlide.layout = {
    breadcrumbs: [
        { title: 'Portada', href: index() },
        { title: 'Nuevo slide', href: create() },
    ],
};
