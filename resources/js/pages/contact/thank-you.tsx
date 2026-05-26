import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function ThankYou() {
    return (
        <>
            <Head title="Merci" />
            <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4 py-10">
                <Card className="max-w-md text-center">
                    <CardHeader>
                        <CardTitle>Merci !</CardTitle>
                        <CardDescription>
                            Votre demande a bien été reçue. Nous reviendrons
                            vers vous au plus vite.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground">
                        Vous pouvez fermer cette page ou envoyer une nouvelle
                        demande.
                    </CardContent>
                    <CardFooter className="justify-center">
                        <Button asChild>
                            <Link href={home.url()}>Retour à l’accueil</Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}
