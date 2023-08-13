<?php

namespace CSVDB\History;

use DateTime;

trait HistoryTrait
{

    public function setup_history(): void
    {
        if ($this->config->history) {
            $dir = $this->history_dir();
            $files = scandir($dir, SCANDIR_SORT_DESCENDING);
            if (is_file($dir . $files[0])) {
                $latest = $files[0];
                if (md5_file($this->file) !== md5_file($dir . $latest)) {
                    $this->history();
                }
            } else {
                $this->history();
            }
        }
    }

    public function history_dir(): string
    {
        $dir = $this->basedir . "/history_".$this->database."/";
        if (!file_exists($dir)) {
            mkdir($dir);
        }
        return $dir;
    }

    private function history(): void
    {
        $dir = $this->history_dir();
        $time = date_format(new DateTime(), "YmdHisu");
        $filename = $dir . $time . "_" . $this->document;
        copy($this->file, $filename);
    }
}