import { Head, useForm, usePage } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useState } from 'react';
import type { FormEventHandler, KeyboardEvent } from 'react';
import SaveArtisanProfileController from '@/actions/App/Http/Controllers/ArtisanProfile/SaveArtisanProfileController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    ArtisanProfilePayload,
    ArtisanProfileProps,
} from '@/types/artisan-profile';

type PageProps = {
    profile: ArtisanProfileProps;
    flash?: { success?: string };
};

export default function EditArtisanProfile() {
    const { props } = usePage<PageProps>();
    const { profile } = props;
    const flashSuccess = props.flash?.success;

    const { data, setData, post, processing, errors, transform } =
        useForm<ArtisanProfilePayload>({
            postalCode: profile?.postalCode ?? '',
            professions: profile?.professions ?? [],
            services: profile?.services ?? '',
        });

    const [professionDraft, setProfessionDraft] = useState('');

    const addProfession = () => {
        const value = professionDraft.trim();

        if (value === '' || data.professions.includes(value)) {
            setProfessionDraft('');

            return;
        }

        setData('professions', [...data.professions, value]);
        setProfessionDraft('');
    };

    const removeProfession = (profession: string) => {
        setData(
            'professions',
            data.professions.filter((item) => item !== profession),
        );
    };

    const handleProfessionKeyDown = (
        event: KeyboardEvent<HTMLInputElement>,
    ) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addProfession();
        }
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        // `services` is nullable backend-side: send null rather than an empty string.
        transform((payload) => ({
            ...payload,
            services: payload.services.trim() === '' ? null : payload.services,
        }));
        post(SaveArtisanProfileController.url());
    };

    return (
        <>
            <Head title="Mon profil" />
            <div className="min-h-screen bg-muted/40 py-10">
                <div className="container mx-auto max-w-2xl px-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Mon profil artisan</CardTitle>
                            <CardDescription>
                                Renseignez votre zone, vos métiers et vos
                                prestations. Ces informations serviront à
                                qualifier vos demandes entrantes.
                            </CardDescription>
                        </CardHeader>

                        {flashSuccess && (
                            <Alert className="mx-6 mb-2 w-auto border-green-600/30 bg-green-600/5 text-green-700 dark:text-green-400">
                                <CircleCheck />
                                <AlertDescription className="text-green-700 dark:text-green-400">
                                    {flashSuccess}
                                </AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={submit} noValidate>
                            <CardContent className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="postalCode">
                                        Code postal
                                    </Label>
                                    <Input
                                        id="postalCode"
                                        type="text"
                                        autoComplete="postal-code"
                                        inputMode="numeric"
                                        maxLength={5}
                                        value={data.postalCode}
                                        onChange={(e) =>
                                            setData(
                                                'postalCode',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.postalCode}
                                        required
                                    />
                                    {errors.postalCode && (
                                        <p className="text-sm text-destructive">
                                            {errors.postalCode}
                                        </p>
                                    )}
                                </div>

                                <fieldset className="space-y-2">
                                    <legend className="text-sm font-medium">
                                        Métiers
                                    </legend>
                                    <p className="text-sm text-muted-foreground">
                                        Ajoutez un ou plusieurs métiers (ex.
                                        plâtrier, peintre). Validez avec Entrée.
                                    </p>
                                    <div className="flex gap-2">
                                        <Input
                                            id="professionDraft"
                                            type="text"
                                            placeholder="Ajouter un métier…"
                                            value={professionDraft}
                                            onChange={(e) =>
                                                setProfessionDraft(
                                                    e.target.value,
                                                )
                                            }
                                            onKeyDown={handleProfessionKeyDown}
                                            aria-invalid={!!errors.professions}
                                            aria-label="Ajouter un métier"
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={addProfession}
                                        >
                                            Ajouter
                                        </Button>
                                    </div>
                                    {data.professions.length > 0 && (
                                        <ul className="flex flex-wrap gap-2 pt-1">
                                            {data.professions.map(
                                                (profession) => (
                                                    <li key={profession}>
                                                        <span className="inline-flex items-center gap-1 rounded-full border border-input bg-background py-1 pr-1 pl-3 text-sm">
                                                            {profession}
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    removeProfession(
                                                                        profession,
                                                                    )
                                                                }
                                                                className="flex size-5 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                                                                aria-label={`Retirer ${profession}`}
                                                            >
                                                                ×
                                                            </button>
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                    {errors.professions && (
                                        <p className="text-sm text-destructive">
                                            {errors.professions}
                                        </p>
                                    )}
                                </fieldset>

                                <div className="space-y-2">
                                    <Label htmlFor="services">
                                        Prestations &amp; tarifs{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optionnel)
                                        </span>
                                    </Label>
                                    <Textarea
                                        id="services"
                                        rows={6}
                                        placeholder="Ex. pose de faux plafond ~ 35 €/m²"
                                        value={data.services}
                                        onChange={(e) =>
                                            setData('services', e.target.value)
                                        }
                                        aria-invalid={!!errors.services}
                                    />
                                    {errors.services && (
                                        <p className="text-sm text-destructive">
                                            {errors.services}
                                        </p>
                                    )}
                                </div>
                            </CardContent>

                            <CardFooter>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                >
                                    {processing
                                        ? 'Enregistrement…'
                                        : 'Enregistrer mon profil'}
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>
                </div>
            </div>
        </>
    );
}
