/**
 * Mirrors the backend contract:
 * @see app/Models/ContactRequest.php
 * @see app/Enums/RequestType.php
 * @see app/Enums/Deadline.php
 * @see app/Data/ListContactRequestsData.php
 */

export type RequestType = 'quote' | 'information' | 'urgent' | 'other';

export type Deadline =
    | 'immediate'
    | 'within_one_month'
    | 'over_one_month'
    | 'not_urgent';

export type Relevance = 'relevant' | 'out_of_area' | 'out_of_trade' | 'spam';

export type LeadQuality = 'complete' | 'incomplete';

export type Priority = 'high' | 'medium' | 'low';

export type SubmitContactRequestPayload = {
    name: string;
    email: string;
    requestType: RequestType | '';
    deadline: Deadline | '';
    description: string;
    phone: string;
    postalCode: string;
    acknowledgeMissingInfo: boolean;
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

export const REQUEST_TYPE_LABELS: Record<RequestType, string> =
    Object.fromEntries(
        REQUEST_TYPE_OPTIONS.map(({ value, label }) => [value, label]),
    ) as Record<RequestType, string>;

export const DEADLINE_LABELS: Record<Deadline, string> = Object.fromEntries(
    DEADLINE_OPTIONS.map(({ value, label }) => [value, label]),
) as Record<Deadline, string>;

export const RELEVANCE_LABELS: Record<Relevance, string> = {
    relevant: 'Pertinente',
    out_of_area: 'Hors zone',
    out_of_trade: 'Hors métier',
    spam: 'Spam',
};

export const LEAD_QUALITY_LABELS: Record<LeadQuality, string> = {
    complete: 'Complet',
    incomplete: 'Incomplet',
};

export const PRIORITY_LABELS: Record<Priority, string> = {
    high: 'Haute',
    medium: 'Moyenne',
    low: 'Basse',
};

/**
 * A contact request as read by the dashboard (snake_case mirrors the Eloquent
 * model's serialised payload; enums are cast to their string value, timestamps
 * to ISO 8601).
 * @see app/Models/ContactRequest.php
 */
export type ContactRequestRow = {
    uuid: string;
    name: string;
    email: string;
    phone: string | null;
    request_type: RequestType;
    deadline: Deadline;
    postal_code: string | null;
    description: string;
    created_at: string;
    // Qualification labels: null until the async job has run (`qualified_at`
    // gates the "en attente de qualification" state).
    missing_info_acknowledged: boolean;
    relevance: Relevance | null;
    project_type: string | null;
    summary: string | null;
    estimated_amount_min: number | null;
    estimated_amount_max: number | null;
    lead_quality: LeadQuality | null;
    priority: Priority | null;
    qualified_at: string | null;
};

/**
 * The active server-side filters echoed back by the controller.
 * @see app/Data/ListContactRequestsData.php
 */
export type ContactRequestFilters = {
    type: RequestType | null;
    deadline: Deadline | null;
};

/**
 * Laravel LengthAwarePaginator serialised shape (top-level meta + `data` rows +
 * `links` for page navigation).
 */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};
