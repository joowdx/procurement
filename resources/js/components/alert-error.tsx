import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircleIcon } from 'lucide-react';

interface AlertErrorProps {
    errors: Record<string, string | string[]> | string[];
    title?: string;
}

export function AlertError({ errors, title }: AlertErrorProps) {
    // Convert errors object to array of error messages
    const errorMessages: string[] = Array.isArray(errors)
        ? errors
        : Object.values(errors).flat();

    if (errorMessages.length === 0) {
        return null;
    }

    return (
        <Alert variant="destructive">
            <AlertCircleIcon className="h-4 w-4" />
            <AlertTitle>{title || 'Something went wrong.'}</AlertTitle>
            <AlertDescription>
                <ul className="list-inside list-disc text-sm">
                    {Array.from(new Set(errorMessages)).map((error, index) => (
                        <li key={index}>{error}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
