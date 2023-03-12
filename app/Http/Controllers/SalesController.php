<?php

namespace App\Http\Controllers;

use App\Jobs\SalesCsvProcess;
use App\Models\Sale;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class SalesController extends Controller
{
    public function index()
    {
        return view('upload-file');
    }

    public function upload()
    {
        if (request()->hasFile('mycsv')) {
            $data = file(request()->mycsv);
            
            $chunks = array_chunk($data, 1000);
            $header = [];
            
            $batch = Bus::batch([])->dispatch();

            foreach ($chunks as $key => $chunk) {
                $data = array_map('str_getcsv', $chunk);
            

                if ($key == 0) {
                    $header = $data[0];
                    unset($data[0]);
                }
                
                
                $batch->add(new SalesCsvProcess($data, $header));
            }
            
            return $batch;
        }
    }

    public function uploadLargeFiles(Request $request) {
        Logger($request->all());
        $receiver = new FileReceiver('file', $request, HandlerFactory::classFromRequest($request));
    
        if (!$receiver->isUploaded()) {
            // file not uploaded
        }
    
        $fileReceived = $receiver->receive(); // receive file
        if ($fileReceived->isFinished()) { // file uploading is complete / all chunks are uploaded
            $file = $fileReceived->getFile(); // get file
            $extension = 'mp4';
            $fileName = str_replace('.'.$extension, '', $file->getClientOriginalName()); //file name without extenstion
            $fileName .= '_' . md5(time()) . '.' . $extension; // a unique file name
            
            $move_path = "public\\movies\\";
            
            $file->storeAs($move_path, $fileName);
            // Storage::disk('local')->put($move_path, $file);
    
            // delete chunked file
            unlink($file->getPathname());
            return [
                'extention' => $extension,
                'path' => asset('storage/movies/' . $fileName),
                'filename' => $fileName
            ];
        }
    
        // otherwise return percentage information
        $handler = $fileReceived->handler();
        return [
            'done' => $handler->getPercentageDone(),
            'status' => true
        ];
    }
}
