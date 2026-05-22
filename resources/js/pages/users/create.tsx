import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

type PageProps = {
    flash?: { success?: string };
};

export default function CreateUser() {
    const { props } = usePage<PageProps>();
    const flashSuccess = props.flash?.success;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        firstName: '',
        lastName: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post('/users', {
            onSuccess: () => reset(),
        });
    };

    return (
        <>
            <Head title="Créer un utilisateur" />
            <div className="flex min-h-screen items-center justify-center bg-gray-50 p-6 dark:bg-gray-900">
                <div className="w-full max-w-md rounded-lg bg-white p-8 shadow dark:bg-gray-800">
                    <h1 className="mb-6 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        Créer un utilisateur
                    </h1>

                    {flashSuccess && (
                        <div className="mb-4 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                            {flashSuccess}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4" noValidate>
                        <div>
                            <label
                                htmlFor="email"
                                className="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Email
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                className="mt-1 block w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                required
                            />
                            {errors.email && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="firstName"
                                className="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Prénom
                            </label>
                            <input
                                id="firstName"
                                type="text"
                                value={data.firstName}
                                onChange={(e) =>
                                    setData('firstName', e.target.value)
                                }
                                className="mt-1 block w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                            />
                            {errors.firstName && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.firstName}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="lastName"
                                className="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Nom
                            </label>
                            <input
                                id="lastName"
                                type="text"
                                value={data.lastName}
                                onChange={(e) =>
                                    setData('lastName', e.target.value)
                                }
                                className="mt-1 block w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                            />
                            {errors.lastName && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.lastName}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {processing ? 'Création…' : 'Créer'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
