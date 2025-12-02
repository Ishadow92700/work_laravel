<?php
use Illuminate\Support\Facades\Storage;
use App\Models\SftpFile;

class UploadSftpFile implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $sftpFile;

    public function __construct(SftpFile $sftpFile)
    {
        $this->sftpFile = $sftpFile;
    }

    public function handle()
    {
        try {
            Storage::disk('sftp')->put(
                $this->sftpFile->filename,
                $this->sftpFile->content
            );

            $this->sftpFile->update(['status' => 'uploaded', 'error' => null]);
        } catch (\Exception $e) {
            $this->sftpFile->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
