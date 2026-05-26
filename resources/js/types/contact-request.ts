/**
 * Mirrors the backend Use Case contract:
 * @see app/Application/UseCase/SubmitContactRequest/Request.php
 * @see app/Domain/Model/RequestType.php
 * @see app/Domain/Model/Deadline.php
 */

export type RequestType = 'quote' | 'information' | 'urgent' | 'other';

export type Deadline =
    | 'immediate'
    | 'within_one_month'
    | 'over_one_month'
    | 'not_urgent';

export type SubmitContactRequestPayload = {
    name: string;
    email: string;
    requestType: RequestType | '';
    deadline: Deadline | '';
    description: string;
    phone: string;
    postalCode: string;
};

export const REQUEST_TYPE_OPTIONS: ReadonlyArray<{
    value: RequestType;
    label: string;
    description: string;
}> = [
    {
        value: 'quote',
        label: 'Devis',
        description: 'Vous souhaitez une estimation chiffrée.',
    },
    {
        value: 'information',
        label: 'Information',
        description: 'Vous avez une question avant de vous engager.',
    },
    {
        value: 'urgent',
        label: 'Urgence',
        description: 'Une intervention rapide est nécessaire.',
    },
    {
        value: 'other',
        label: 'Autre',
        description: 'Votre besoin ne rentre dans aucune case.',
    },
];

export const DEADLINE_OPTIONS: ReadonlyArray<{
    value: Deadline;
    label: string;
}> = [
    { value: 'immediate', label: 'Dès que possible' },
    { value: 'within_one_month', label: 'Dans le mois' },
    { value: 'over_one_month', label: 'Dans plus d’un mois' },
    { value: 'not_urgent', label: 'Pas pressé' },
];
