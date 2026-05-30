import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import SubmitContactRequestController from '@/actions/App/Http/Controllers/ContactRequest/SubmitContactRequestController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    DEADLINE_OPTIONS,
    REQUEST_TYPE_OPTIONS,
} from '@/types/contact-request';
import type { SubmitContactRequestPayload } from '@/types/contact-request';

export default function ContactIndex() {
    const { data, setData, post, processing, errors, reset } =
        useForm<SubmitContactRequestPayload>({
            name: '',
            email: '',
            requestType: '',
            deadline: '',
            description: '',
            phone: '',
            postalCode: '',
            acknowledgeMissingInfo: false,
        });

    // The soft sufficiency gate runs server-side only on a non-empty description
    // (form validation rejects an empty one first), so a description error paired
    // with content is the "missing info" message — that's when we reveal the
    // "I can't provide this" escape hatch. Stays visible once acknowledged.
    const showAcknowledge =
        (!!errors.description && data.description.trim() !== '') ||
        data.acknowledgeMissingInfo;

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(SubmitContactRequestController.url(), {
            onSuccess: () => reset(),
        });
    };

    return (
        <>
            <Head title="Votre demande" />
            <div className="min-h-screen bg-muted/40 py-10">
                <div className="container mx-auto max-w-2xl px-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Décrivez votre besoin</CardTitle>
                            <CardDescription>
                                Remplissez ce formulaire et nous revenons vers
                                vous au plus vite.
                            </CardDescription>
                        </CardHeader>

                        <form onSubmit={submit} noValidate>
                            <CardContent className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Nom</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        autoComplete="name"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        aria-invalid={!!errors.name}
                                        required
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-destructive">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        autoComplete="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                        aria-invalid={!!errors.email}
                                        required
                                    />
                                    {errors.email && (
                                        <p className="text-sm text-destructive">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="phone">
                                        Téléphone{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optionnel)
                                        </span>
                                    </Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        autoComplete="tel"
                                        value={data.phone}
                                        onChange={(e) =>
                                            setData('phone', e.target.value)
                                        }
                                        aria-invalid={!!errors.phone}
                                    />
                                    {errors.phone && (
                                        <p className="text-sm text-destructive">
                                            {errors.phone}
                                        </p>
                                    )}
                                </div>

                                <fieldset className="space-y-3">
                                    <legend className="text-sm font-medium">
                                        Type de demande
                                    </legend>
                                    <RadioGroup
                                        value={data.requestType}
                                        onValueChange={(value) =>
                                            setData(
                                                'requestType',
                                                value as SubmitContactRequestPayload['requestType'],
                                            )
                                        }
                                        className="grid gap-3 sm:grid-cols-2"
                                        aria-invalid={!!errors.requestType}
                                    >
                                        {REQUEST_TYPE_OPTIONS.map((option) => (
                                            <Label
                                                key={option.value}
                                                htmlFor={`requestType-${option.value}`}
                                                className={cn(
                                                    'flex cursor-pointer items-start gap-3 rounded-md border border-input p-4 font-normal',
                                                    'has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5',
                                                )}
                                            >
                                                <RadioGroupItem
                                                    id={`requestType-${option.value}`}
                                                    value={option.value}
                                                    className="mt-0.5"
                                                />
                                                <span className="space-y-1">
                                                    <span className="block text-sm font-medium">
                                                        {option.label}
                                                    </span>
                                                    <span className="block text-sm text-muted-foreground">
                                                        {option.description}
                                                    </span>
                                                </span>
                                            </Label>
                                        ))}
                                    </RadioGroup>
                                    {errors.requestType && (
                                        <p className="text-sm text-destructive">
                                            {errors.requestType}
                                        </p>
                                    )}
                                </fieldset>

                                <fieldset className="space-y-3">
                                    <legend className="text-sm font-medium">
                                        Délai souhaité
                                    </legend>
                                    <RadioGroup
                                        value={data.deadline}
                                        onValueChange={(value) =>
                                            setData(
                                                'deadline',
                                                value as SubmitContactRequestPayload['deadline'],
                                            )
                                        }
                                        className="space-y-2"
                                        aria-invalid={!!errors.deadline}
                                    >
                                        {DEADLINE_OPTIONS.map((option) => (
                                            <div
                                                key={option.value}
                                                className="flex items-center gap-2"
                                            >
                                                <RadioGroupItem
                                                    id={`deadline-${option.value}`}
                                                    value={option.value}
                                                />
                                                <Label
                                                    htmlFor={`deadline-${option.value}`}
                                                    className="font-normal"
                                                >
                                                    {option.label}
                                                </Label>
                                            </div>
                                        ))}
                                    </RadioGroup>
                                    {errors.deadline && (
                                        <p className="text-sm text-destructive">
                                            {errors.deadline}
                                        </p>
                                    )}
                                </fieldset>

                                <div className="space-y-2">
                                    <Label htmlFor="postalCode">
                                        Code postal{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optionnel)
                                        </span>
                                    </Label>
                                    <Input
                                        id="postalCode"
                                        type="text"
                                        autoComplete="postal-code"
                                        inputMode="numeric"
                                        value={data.postalCode}
                                        onChange={(e) =>
                                            setData(
                                                'postalCode',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.postalCode}
                                    />
                                    {errors.postalCode && (
                                        <p className="text-sm text-destructive">
                                            {errors.postalCode}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="description">
                                        Votre besoin
                                    </Label>
                                    <Textarea
                                        id="description"
                                        rows={6}
                                        value={data.description}
                                        onChange={(e) =>
                                            setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.description}
                                        required
                                    />
                                    {errors.description && (
                                        <p className="text-sm text-destructive">
                                            {errors.description}
                                        </p>
                                    )}
                                </div>

                                {showAcknowledge && (
                                    <div className="flex items-start gap-3 rounded-md border border-input bg-muted/40 p-4">
                                        <Checkbox
                                            id="acknowledgeMissingInfo"
                                            checked={
                                                data.acknowledgeMissingInfo
                                            }
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'acknowledgeMissingInfo',
                                                    checked === true,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
                                        <Label
                                            htmlFor="acknowledgeMissingInfo"
                                            className="font-normal"
                                        >
                                            Je ne suis pas en mesure de fournir
                                            ces informations — envoyez ma
                                            demande telle quelle.
                                        </Label>
                                    </div>
                                )}
                            </CardContent>

                            <CardFooter>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                >
                                    {processing
                                        ? 'Envoi…'
                                        : 'Envoyer ma demande'}
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>
                </div>
            </div>
        </>
    );
}
