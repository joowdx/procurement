/**
 * Check if a file can be previewed in the browser
 * Based on what the backend can serve and browsers can display natively
 */
export function isPreviewable(file: { type: string; extension: string }): boolean {
    const type = file.type.toLowerCase();
    const ext = file.extension.toLowerCase();

    // Images (natively supported by browsers)
    if (type.startsWith('image/')) {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
    }

    // PDFs (browsers have native PDF viewer)
    if (type === 'application/pdf' || ext === 'pdf') {
        return true;
    }

    // Text files (browsers can display)
    if (type.startsWith('text/')) {
        return true;
    }

    return false;
}

/**
 * Get file icon color based on type
 */
export function getFileIconColor(extension: string): string {
    const ext = extension.toLowerCase();
    
    // Images
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) {
        return 'text-green-600 dark:text-green-400';
    }
    
    // PDFs
    if (ext === 'pdf') {
        return 'text-red-600 dark:text-red-400';
    }
    
    // Documents
    if (['doc', 'docx'].includes(ext)) {
        return 'text-blue-600 dark:text-blue-400';
    }
    
    // Spreadsheets
    if (['xls', 'xlsx', 'csv'].includes(ext)) {
        return 'text-green-700 dark:text-green-500';
    }
    
    // Presentations
    if (['ppt', 'pptx'].includes(ext)) {
        return 'text-orange-600 dark:text-orange-400';
    }
    
    // Archives
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) {
        return 'text-purple-600 dark:text-purple-400';
    }
    
    // Default
    return 'text-blue-600 dark:text-blue-400';
}

