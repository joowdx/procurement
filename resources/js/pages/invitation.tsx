import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function Invitation() {
    const handleLogout = () => {
        // This will be handled by the logout route
        window.location.href = '/logout';
    };

    return (
        <>
            <Head title="No Workspaces Available" />
            
            <div className="min-h-screen flex items-center justify-center bg-background p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <CardTitle className="text-2xl font-bold">No Workspaces Available</CardTitle>
                        <CardDescription>
                            You don't have access to any workspaces yet.
                        </CardDescription>
                    </CardHeader>
                    
                    <CardContent className="space-y-4">
                        <div className="text-center text-muted-foreground">
                            <p className="mb-4">
                                Please wait to be invited to a workspace by an administrator or workspace owner.
                            </p>
                            
                            <p className="text-sm">
                                If you believe this is an error, please contact your system administrator.
                            </p>
                        </div>
                        
                        <div className="flex justify-center">
                            <Button 
                                variant="outline" 
                                onClick={handleLogout}
                                className="w-full"
                            >
                                Sign Out
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
