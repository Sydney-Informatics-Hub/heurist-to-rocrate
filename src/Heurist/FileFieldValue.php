<?php

namespace UtilityCli\Heurist;

class FileFieldValue extends GenericFieldValue
{
    /**
     * @var string $id
     *   The Heurist ID of the file.
     */
    protected string $id;

    /**
     * @var string $fileName
     *   The original file name.
     */
    protected string $fileName;

    /**
     * @var string $mimeType
     *   The MIME type of the file.
     */
    protected string $mimeType;

    /**
     * @var int $fileSize
     *   The size of the file (in bytes).
     */
    protected int $fileSize;

    /**
     * @var string $date
     *   The date of the file (in ISO 8601 format).
     */
    protected string $date;

    /**
     * @var string $url
     *   The URL of the file.
     */
    protected string $url;

    /**
     * Get the Heurist ID of the file.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the Heurist ID of the file.
     *
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the original file name.
     *
     * @return string|null
     */
    public function getFileName(): ?string
    {
        return $this->fileName ?? null;
    }

    /**
     * Set the original file name.
     *
     * @param string $fileName
     */
    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    /**
     * Get the MIME type of the file.
     *
     * @return string|null
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType ?? null;
    }

    /**
     * Set the MIME type of the file.
     *
     * @param string $mimeType
     */
    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    /**
     * Get the size of the file (in bytes).
     *
     * @return int|null
     */
    public function getFileSize(): ?int
    {
        return $this->fileSize ?? null;
    }

    /**
     * Set the size of the file.
     *
     * @param int $fileSize
     */
    public function setFileSize(int $fileSize, string $unit = 'B'): void
    {
        if (is_string($unit)) {
            $unit = strtoupper($unit);
        }
        if ($unit === 'KB') {
            $fileSize = $fileSize * 1024;
        } elseif ($unit === 'MB') {
            $fileSize = $fileSize * 1024 * 1024;
        } elseif ($unit === 'GB') {
            $fileSize = $fileSize * 1024 * 1024 * 1024;
        }
        $this->fileSize = $fileSize;
    }

    /**
     * Get the date of the file (in ISO 8601 format).
     *
     * @return string|null
     */
    public function getDate(): ?string
    {
        return $this->date ?? null;
    }

    /**
     * Set the date of the file.
     * @param string $date
     */
    public function setDate(string $date): void
    {
        $datetime = new \DateTime($date);
        $this->date = $datetime->format(\DateTime::ATOM);
    }

    /**
     * Get the URL of the file.
     *
     * @return string|null
     *
     */
    public function getUrl(): ?string
    {
        return $this->url ?? null;
    }

    /**
     * Set the URL of the file.
     *
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return bool
     */
    public function isRemote(): bool
    {
        return $this->fileName === '_remote';
    }

    /**
     * Get the local stored file name.
     *
     * @return string|null
     */
    public function getLocalName(): ?string
    {
        if (isset($this->id) && isset($this->fileName) && !$this->isRemote()) {
            return 'ulf_' . $this->id . '_' . $this->fileName;
        }
        return null;
    }

}