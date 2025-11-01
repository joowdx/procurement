import { Head } from '@inertiajs/react';
import { File } from '@/types';

interface Props {
  file: File;
}

export default function Show({ file }: Props) {
  return (
    <>
      <Head title={`File: ${file.name}`} />
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900 dark:text-gray-100">
              <h1 className="text-2xl font-bold mb-4">{file.name}</h1>
              <p className="text-gray-600 dark:text-gray-400">{file.description}</p>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}







