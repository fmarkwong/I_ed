<?php
  /**
   * FileProcessor
   * 
   * Handle uploading, resizing, storing and displaying of a file (image or document)
   * associated with a nomination. 
   * 
   * Example usage:
   *    foreach (range(1, 6) as $fileNumber) {
   *        $fileProcessor = new FileProcessor($nominationId, $_FILES["file$fileNumber"], $fileNumber);
   *   
   *        if (! $fileProcessor->fileIsUploaded()) {
   *            continue; 
   *        }
   *        if ($fileProcessor->fileIsImage()) {
   *            $fileProcessor->processImage();
   *        } else {
   *            $fileProcessor->processDocument();
   *        }
   *    }
   *
   * @author Mark Wong 
   */

class FileProcessor
{
        /**
         * Constructor 
         *
         * @param int $nominationId
         * @param array $file File metadata from $_FILES['filename']
         * @param int $fileNumber Numerical identifier of one of 6 
         * files (1 - 6) of the current nomination.
         */
        public function __construct($nominationId = null, $file = null, $fileNumber = null)
        {
            $this->nominationId = $nominationId;
            $this->uploadFolder = UPLOAD_FOLDER . "/$nominationId";
            $this->fileNumber = $fileNumber;

            $this->imgHandle = new upload($file); 
        }

        /**
         * Resize image if necessary and save path to DB
         *
         */
        public function processImage()
        {
            $resize = $this->getImageWidth() > MAXIMUM_UPLOADED_IMAGE_WIDTH ? 'small-version' : null; 
            $success = $this->saveImage($resize);

            $this->filePathName = $this->getPartiallyQualifiedImageFileName();
            $this->saveFilePathNameToDB();
        }

        /**
         * Save Image to filesystem
         *
         * @param bool $resize Resize the array or not
         *
         * @return bool Success or not
         */
        public function saveImage($resize = null)
        {
            if (!$this->imgHandle->uploaded) {
                echo 'File has not been uploaded';
                return false;
            }

            if ($resize === 'small-version') {
                $this->configureForSmallImage();
            }

            $this->imgHandle->process($this->uploadFolder);
            if ($this->imgHandle->processed) {
                return true;
            } else {
                echo "File Upload Error : {$this->imgHandle->error}. Please try again\n";
                return false;
            }
        }

        /**
         * Whether a file has been uploaded already 
         *
         * @return bool
         */
        public function fileIsUploaded()
        {
            return $this->imgHandle->uploaded; 
        }

        /**
         * Whether a file is an image 
         *
         * @return bool  
         */
        public function fileIsImage()
        {
            return $this->imgHandle->file_is_image; 
        }

        /**
         * Save pathname of file to DB 
         *
         * @return bool Success or not 
         */
        public function saveFilePathNameToDB()
        {
            $sql = "UPDATE nominations SET file$this->fileNumber = :fpn WHERE id = :id";
            $sth = getPDOConnection()->prepare($sql);
            return $sth->execute([
                            ':fpn'  => $this->filePathName,
                            ':id'   => $this->nominationId
                          ]);
        }

        /**
         * Compile Filepath name data
         *
         * Retrieves file path data from DB and stores in
         * appropriate class property (image or document)
         *
         * @return bool Success or not 
         */
        public function compileFilePathNames()
        {
            $imgCounter = 0;
            $docCounter = 0;

            $sql = "SELECT file1, file2, file3, file4, file5, file6 FROM nominations WHERE id = :id";
            $sth = getPDOConnection()->prepare($sql);
            $sth->execute([':id' => $this->nominationId]);
            $result = $sth->fetch(PDO::FETCH_ASSOC);

            foreach( range(1, 6) as $fileNumber) {
                $fileRelativePathName = $result["file$fileNumber"];

                if (empty($fileRelativePathName)) continue;

                $fileHandler = new upload(APP_ROOT_DIR . "/$fileRelativePathName");

                if ($fileHandler->file_is_image) {
                    $this->filePathNames['images'][$imgCounter++] = $fileRelativePathName;
                } else {
                    $this->filePathNames['documents'][$docCounter++] = $fileRelativePathName;
                }
            }
        }

        /**
         * Get list of image pathnames for current nomination 
         *
         * @return array List of image pathnames
         */
        public function getImagePathNames()
        {
            return $this->filePathNames['images'];
        }
        
        /**
         * Get list of document pathnames for current nomination 
         *
         * @return array List of document pathnames
         */
        public function getDocumentPathNames()
        {
            return $this->filePathNames['documents'];
        }

        /**
         * Display images for current nomination 
         *
         * @return array Whether there were images to display 
         */
        public function displayImages() 
        {
            if (empty($this->filePathNames['images'])) return false;

            foreach ($this->filePathNames['images'] as $imageFilePathName) {
                if (!empty($imageFilePathName)) {
                    echo "<img src ='/nominate-form/$imageFilePathName' style='max-width: ".MAXIMUM_UPLOADED_IMAGE_WIDTH."px; max-height: 1200px; height: auto'>";
                    echo "<br><br>";
                }
            }

            return true;
        }


        /**
         * Extract filename from relative file path 
         *
         * @param string $relativeFileName  
         *
         * @return string Filename 
         */
        public static function getFileNameFromRelativeFilePath($relativeFileName)
        {
            $arr = explode('/', $relativeFileName);
            return array_pop($arr);
        }

        /**
         * Get image width of current image 
         *
         * @param string $relativeFileName  
         *
         * @return int Width in pixels 
         */
        public function getImageWidth()
        {
            return $this->imgHandle->image_src_x;
        }
        
        /**
         * Create small version of all images.
         *
         * Retrieves the images of every file of every nomination,
         * makes smaller version of each, stores in filesystem
         * and saves file path in DB 
         *
         * Example usage:
         * (new FileProcessor())->makeSmallVersionOfExistingImages();
         *
         * Note:  This should only be done once per new deployment so that
         * existing images have a smaller version.  After that, new images
         * should have their smaller version made on the fly. 
         */
        public function makeSmallVersionOfExistingImages()
        {
            $sql = "SELECT id, file1, file2, file3, file4, file5, file6 FROM nominations";
            $sth = getPDOConnection()->prepare($sql);
            $sth->execute();
            $nominations = $sth->fetchAll();

            foreach ($nominations as $nomination) {
                foreach( range(1, 6) as $fileNumber) {
                    $fileRelativePathName = $nomination["file$fileNumber"];

                    if (empty($fileRelativePathName)) continue;

                    $fullyQualifiedPathName = APP_ROOT_DIR . "/$fileRelativePathName";
                    $this->imgHandle = new upload($fullyQualifiedPathName);

                    if ($this->imgHandle->file_is_image && $this->notSmallImage()) {
                        $this->imgHandle->file_max_size = '50M';
                        $this->uploadFolder = dirname($fullyQualifiedPathName);
                        if ($this->saveImage('small-version')) {
                            $this->nominationId = $nomination['id'];
                            $this->fileNumber = $fileNumber;
                            $this->filePathName = $this->getPartiallyQualifiedImageFileName();
                            echo "Sucessfully shrunk and saved image file$this->fileNumber of nomination id: $this->nominationId, filename: $this->filePathName, size: {$this->imgHandle->file_src_size}\n";
                            $this->saveFilePathNameToDB();
                        } 
                    }
                }
            }
        }

        /**
         * Whether image is small version
         *
         * @return bool True if not small version 
         */
        private function notSmallImage()
        {
            return substr($this->imgHandle->file_src_name_body, -5) !== 'small';
        }

        /**
         * Get partially qualified document file name 
         *
         * e.g. 'uploads/34/house.docx'
         *
         * @return string Filename path 
         */
        private function getPartiallyQualifiedDocumentFileName()
        {
            return "uploads/$this->nominationId/{$this->imgHandle->file_src_name}";
        }
        
        /**
         * Get partially qualified imaage file name 
         *
         * e.g. 'uploads/34/house.jpg'
         *
         * @return string Image path 
         */
        private function getPartiallyQualifiedImageFileName()
        {
            $result = explode('/', $this->imgHandle->file_dst_pathname);
            $result = array_slice($result, -3);
            return implode('/', $result);
        }

        /**
         * Set properties to prepare for creating small image version 
         *
         */
        private function configureForSmallImage()
        {
            $this->imgHandle->file_name_body_add   = '_small'; 
            $this->imgHandle->image_resize         = true;
            $this->imgHandle->image_x              = MAXIMUM_UPLOADED_IMAGE_WIDTH;
            $this->imgHandle->image_ratio_y        = true;
            $this->imgHandle->file_overwrite       = true;
        }
}
