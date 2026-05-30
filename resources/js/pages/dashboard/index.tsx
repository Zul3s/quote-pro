import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { Fragment, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    DEADLINE_LABELS,
    DEADLINE_OPTIONS,
    LEAD_QUALITY_LABELS,
    PRIORITY_LABELS,
    RELEVANCE_LABELS,
    REQUEST_TYPE_LABELS,
    REQUEST_TYPE_OPTIONS,
} from '@/types/contact-request';
import type {
    ContactRequestFilters,
    ContactRequestRow,
    Deadline,
    Priority,
    Relevance,
    Paginated,
    RequestType,
} from '@/types/contact-request';

type PageProps = {
    contactRequests: Paginated<ContactRequestRow>;
    filters: ContactRequestFilters;
};

// shadcn's Select forbids an empty-string item value, so a sentinel stands in
// for "no filter" and is dropped before navigating.
const ALL = 'all';

const dateFormatter = new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const amountFormatter = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
});

const PRIORITY_VARIANT: Record<
    Priority,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    high: 'destructive',
    medium: 'default',
    low: 'secondary',
};

const RELEVANCE_VARIANT: Record<
    Relevance,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    relevant: 'secondary',
    out_of_area: 'outline',
    out_of_trade: 'outline',
    spam: 'destructive',
};

// Internal-only estimate band shown to the artisan: a missing-info-acknowledged
// lead can legitimately have no figure, so absence reads as "non chiffrable"
// rather than an error.
const formatEstimate = (row: ContactRequestRow): string | null => {
    const { estimated_amount_min: min, estimated_amount_max: max } = row;

    if (min === null && max === null) {
        return null;
    }

    if (min !== null && max !== null) {
        return min === max
            ? amountFormatter.format(min)
            : `${amountFormatter.format(min)} – ${amountFormatter.format(max)}`;
    }

    return amountFormatter.format((min ?? max) as number);
};

export default function ContactRequestDashboard() {
    const { props } = usePage<PageProps>();
    const { contactRequests, filters } = props;
    const rows = contactRequests.data;

    const [expanded, setExpanded] = useState<string | null>(null);

    const navigate = (next: ContactRequestFilters) => {
        router.get(
            dashboard.url(),
            {
                ...(next.type ? { type: next.type } : {}),
                ...(next.deadline ? { deadline: next.deadline } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const onTypeChange = (value: string) =>
        navigate({
            ...filters,
            type: value === ALL ? null : (value as RequestType),
        });

    const onDeadlineChange = (value: string) =>
        navigate({
            ...filters,
            deadline: value === ALL ? null : (value as Deadline),
        });

    const toggle = (uuid: string) =>
        setExpanded((current) => (current === uuid ? null : uuid));

    return (
        <>
            <Head title="Demandes reçues" />
            <div className="min-h-screen bg-muted/40 py-10">
                <div className="container mx-auto max-w-5xl px-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Demandes reçues</CardTitle>
                            <CardDescription>
                                {contactRequests.total} demande
                                {contactRequests.total > 1 ? 's' : ''} au total.
                                Cliquez sur une ligne pour en voir le détail.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="flex flex-wrap gap-4">
                                <div className="space-y-1.5">
                                    <span className="text-sm font-medium">
                                        Type de demande
                                    </span>
                                    <Select
                                        value={filters.type ?? ALL}
                                        onValueChange={onTypeChange}
                                    >
                                        <SelectTrigger className="w-56">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ALL}>
                                                Tous les types
                                            </SelectItem>
                                            {REQUEST_TYPE_OPTIONS.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <span className="text-sm font-medium">
                                        Délai souhaité
                                    </span>
                                    <Select
                                        value={filters.deadline ?? ALL}
                                        onValueChange={onDeadlineChange}
                                    >
                                        <SelectTrigger className="w-56">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ALL}>
                                                Tous les délais
                                            </SelectItem>
                                            {DEADLINE_OPTIONS.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {rows.length === 0 ? (
                                <div className="rounded-md border border-dashed py-16 text-center text-muted-foreground">
                                    Aucune demande pour ces critères.
                                </div>
                            ) : (
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-8" />
                                                <TableHead>Nom</TableHead>
                                                <TableHead>Priorité</TableHead>
                                                <TableHead>Type</TableHead>
                                                <TableHead>Délai</TableHead>
                                                <TableHead>Reçue le</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {rows.map((row) => {
                                                const isOpen =
                                                    expanded === row.uuid;

                                                return (
                                                    <Fragment key={row.uuid}>
                                                        <TableRow
                                                            className="cursor-pointer"
                                                            onClick={() =>
                                                                toggle(row.uuid)
                                                            }
                                                            aria-expanded={
                                                                isOpen
                                                            }
                                                        >
                                                            <TableCell>
                                                                <ChevronDown
                                                                    className={cn(
                                                                        'size-4 text-muted-foreground transition-transform',
                                                                        isOpen &&
                                                                            'rotate-180',
                                                                    )}
                                                                />
                                                            </TableCell>
                                                            <TableCell className="font-medium">
                                                                <span className="block">
                                                                    {row.name}
                                                                </span>
                                                                <span className="mt-1 flex flex-wrap gap-1">
                                                                    {row.relevance && (
                                                                        <Badge
                                                                            variant={
                                                                                RELEVANCE_VARIANT[
                                                                                    row
                                                                                        .relevance
                                                                                ]
                                                                            }
                                                                        >
                                                                            {
                                                                                RELEVANCE_LABELS[
                                                                                    row
                                                                                        .relevance
                                                                                ]
                                                                            }
                                                                        </Badge>
                                                                    )}
                                                                    {row.lead_quality && (
                                                                        <Badge variant="outline">
                                                                            {
                                                                                LEAD_QUALITY_LABELS[
                                                                                    row
                                                                                        .lead_quality
                                                                                ]
                                                                            }
                                                                        </Badge>
                                                                    )}
                                                                    {row.missing_info_acknowledged && (
                                                                        <Badge variant="outline">
                                                                            Infos
                                                                            incomplètes
                                                                            assumées
                                                                        </Badge>
                                                                    )}
                                                                </span>
                                                            </TableCell>
                                                            <TableCell>
                                                                {row.qualified_at ===
                                                                null ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="text-muted-foreground"
                                                                    >
                                                                        En
                                                                        attente
                                                                    </Badge>
                                                                ) : row.priority ? (
                                                                    <Badge
                                                                        variant={
                                                                            PRIORITY_VARIANT[
                                                                                row
                                                                                    .priority
                                                                            ]
                                                                        }
                                                                    >
                                                                        {
                                                                            PRIORITY_LABELS[
                                                                                row
                                                                                    .priority
                                                                            ]
                                                                        }
                                                                    </Badge>
                                                                ) : (
                                                                    '—'
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge variant="secondary">
                                                                    {
                                                                        REQUEST_TYPE_LABELS[
                                                                            row
                                                                                .request_type
                                                                        ]
                                                                    }
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge variant="outline">
                                                                    {
                                                                        DEADLINE_LABELS[
                                                                            row
                                                                                .deadline
                                                                        ]
                                                                    }
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                                                {dateFormatter.format(
                                                                    new Date(
                                                                        row.created_at,
                                                                    ),
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                        {isOpen && (
                                                            <TableRow className="bg-muted/30 hover:bg-muted/30">
                                                                <TableCell />
                                                                <TableCell
                                                                    colSpan={5}
                                                                    className="space-y-3 py-4"
                                                                >
                                                                    <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                                                        <div>
                                                                            <dt className="font-medium">
                                                                                Email
                                                                            </dt>
                                                                            <dd className="text-muted-foreground">
                                                                                {
                                                                                    row.email
                                                                                }
                                                                            </dd>
                                                                        </div>
                                                                        <div>
                                                                            <dt className="font-medium">
                                                                                Téléphone
                                                                            </dt>
                                                                            <dd className="text-muted-foreground">
                                                                                {row.phone ??
                                                                                    '—'}
                                                                            </dd>
                                                                        </div>
                                                                        <div>
                                                                            <dt className="font-medium">
                                                                                Code
                                                                                postal
                                                                            </dt>
                                                                            <dd className="text-muted-foreground">
                                                                                {row.postal_code ??
                                                                                    '—'}
                                                                            </dd>
                                                                        </div>
                                                                        <div>
                                                                            <dt className="font-medium">
                                                                                Type
                                                                                de
                                                                                projet
                                                                            </dt>
                                                                            <dd className="text-muted-foreground">
                                                                                {row.project_type ??
                                                                                    '—'}
                                                                            </dd>
                                                                        </div>
                                                                        <div>
                                                                            <dt className="font-medium">
                                                                                Estimation{' '}
                                                                                <span className="font-normal text-muted-foreground">
                                                                                    (interne)
                                                                                </span>
                                                                            </dt>
                                                                            <dd className="text-muted-foreground">
                                                                                {formatEstimate(
                                                                                    row,
                                                                                ) ??
                                                                                    'Non chiffrable'}
                                                                            </dd>
                                                                        </div>
                                                                    </dl>
                                                                    {row.summary && (
                                                                        <div className="space-y-1 text-sm">
                                                                            <p className="font-medium">
                                                                                Résumé
                                                                            </p>
                                                                            <p className="text-muted-foreground">
                                                                                {
                                                                                    row.summary
                                                                                }
                                                                            </p>
                                                                        </div>
                                                                    )}
                                                                    <div className="space-y-1 text-sm">
                                                                        <p className="font-medium">
                                                                            Besoin
                                                                        </p>
                                                                        <p className="whitespace-pre-line text-muted-foreground">
                                                                            {
                                                                                row.description
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                </TableCell>
                                                            </TableRow>
                                                        )}
                                                    </Fragment>
                                                );
                                            })}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}

                            {contactRequests.last_page > 1 && (
                                <nav
                                    className="flex flex-wrap items-center justify-center gap-1"
                                    aria-label="Pagination"
                                >
                                    {contactRequests.links.map((link) =>
                                        link.url ? (
                                            <Link
                                                key={link.label}
                                                href={link.url}
                                                preserveState
                                                preserveScroll
                                                className={cn(
                                                    'inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm',
                                                    link.active
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-input hover:bg-muted',
                                                )}
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        ) : (
                                            <span
                                                key={link.label}
                                                className="inline-flex h-9 min-w-9 items-center justify-center rounded-md border border-input px-3 text-sm text-muted-foreground opacity-50"
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        ),
                                    )}
                                </nav>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
