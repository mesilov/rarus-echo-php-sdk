# cli-media-preparation Specification

## Purpose
Let users submit a local video through the existing CLI while automatically preparing and uploading its audio with predictable stream selection, timeline handling and resource ownership.

## Requirements

### Requirement: Automatic video audio preparation

By default, `submit` SHALL prepare an audio-only file for each supported video input before sending the request to Echo. It MUST preserve the original file. Existing supported audio-only inputs MUST pass through without transcoding or a media-tool requirement. Container inspection MUST distinguish real video from attached artwork or an audio-only container.

#### Scenario: Submit a local video in one command

- **WHEN** a user runs `rarus-echo submit recording.mp4 --wait --output=transcript.txt` on a supported video
- **THEN** the CLI prepares its audio, submits only that audio and continues the existing wait/output workflow
- **AND** the original video remains unchanged

#### Scenario: Submit an ordinary audio file

- **WHEN** a user submits a currently supported audio-only file without media-selection options
- **THEN** its existing file bytes are submitted without transcoding and without requiring or executing FFmpeg

#### Scenario: Audio-only or artwork-bearing media container

- **WHEN** inspection of an ambiguous media container finds audio but no real video stream
- **THEN** attached artwork alone does not trigger video extraction and the existing audio upload path is retained

### Requirement: Explicit extraction bypass

`submit --no-extract-audio` SHALL preserve direct upload of the supplied files under the existing SDK validation rules without invoking media tools. It MUST NOT be combined with `--audio-stream`. Preparation failures MUST NOT enable this bypass automatically.

#### Scenario: User explicitly requests direct video upload

- **WHEN** a user submits a video with `--no-extract-audio`
- **THEN** the CLI sends the original file through the previous validation/upload path without probing, conversion or hidden fallback

#### Scenario: Conflicting extraction options

- **WHEN** a user combines `--no-extract-audio` with `--audio-stream`
- **THEN** the command rejects the options before preparing or uploading any file

### Requirement: Deterministic audio selection

For each video the CLI SHALL select the only audio stream marked default, or otherwise the audio stream with the lowest absolute index. `--audio-stream=<index>` MUST select an existing audio stream by its non-negative absolute probe index and MUST be restricted to a single video input. Recognition language MUST NOT implicitly change the selected track.

#### Scenario: Default audio is not the first audio stream

- **WHEN** the second audio stream is the only stream marked default
- **THEN** automatic preparation uses that stream and identifies the selection in stderr

#### Scenario: No unique default audio stream

- **WHEN** no audio stream or multiple audio streams are marked default and at least one audio stream exists
- **THEN** preparation selects the audio stream with the lowest absolute index

#### Scenario: Explicit valid audio stream

- **WHEN** a single video is submitted with an existing audio stream's absolute index
- **THEN** preparation uses that exact stream regardless of its default disposition

#### Scenario: Invalid override or video without audio

- **WHEN** the override is malformed, refers to a non-audio/missing stream, is used for multiple files or audio-only input, or a video has no audio
- **THEN** submission fails with a concise diagnostic before any upload

### Requirement: Verified output format policy

Preparation SHALL copy encoded audio when its codec/container and timeline are accepted by the committed Echo compatibility matrix. Otherwise it SHALL transcode a supported audio codec to the matrix's verified lossless fallback. It MUST preserve channel count, sample rate and supported integer source precision, and MUST reject source float PCM or lossless precision that the verified fallback cannot represent before upload.

#### Scenario: Compatible stream copy

- **WHEN** the selected stream has a verified copy destination and needs no timeline normalization
- **THEN** the output contains only that audio stream with identical encoded audio packets

#### Scenario: Audio conversion is required

- **WHEN** copy is unsuitable but the input decoder and lossless fallback are verified
- **THEN** preparation converts the audio without introducing lossy compression, resampling or channel mixing

#### Scenario: No verified conversion route

- **WHEN** the stream's codec or required output route is outside the accepted matrix
- **THEN** the command explains the unsupported route and sends neither a partial output nor the original video

### Requirement: Source timeline preservation

Prepared audio SHALL represent the selected stream on the original container's playback timeline, including leading silence where required. The local decoded-signal alignment tolerance MUST be at most 50 ms. If timing cannot be determined or preserved within that tolerance, preparation MUST fail rather than silently shifting or truncating speech.

#### Scenario: Audio begins later than video playback

- **WHEN** the selected audio starts after the container's playback origin
- **THEN** preparation preserves that gap as decoded silence in a verified output route instead of rebasing speech to zero

#### Scenario: Timeline cannot be represented safely

- **WHEN** timing metadata is ambiguous or the supported conversion cannot preserve the source timeline
- **THEN** the CLI reports the timing limitation before upload
- **AND** this tolerance is not presented as a guarantee of Echo's recognition timestamp accuracy

### Requirement: Batch preparation before submission

The CLI SHALL validate options and prepare all inputs before making the transcription submission request. The resulting files MUST retain the original input order and one-to-one association with returned file identifiers. A local preparation failure MUST prevent the batch upload and clean already prepared files.

#### Scenario: Mixed batch succeeds

- **WHEN** a batch contains supported audio files and videos that all prepare successfully
- **THEN** the SDK receives one ordered list of original audio and prepared video-audio files
- **AND** the existing `file_ids` and wait-result order correspond to the original arguments

#### Scenario: Later input fails to prepare

- **WHEN** an earlier video has prepared successfully but a later input fails local validation or preparation
- **THEN** no file in the batch is submitted and the earlier temporary output is removed

### Requirement: Bounded preparation resources

`--media-timeout=<seconds>` SHALL set a positive per-input deadline for probe and preparation, defaulting to 600 seconds, independently of polling and HTTP timeouts. Prepared files MUST satisfy the existing SDK upload-size limit. Total temporary output MUST be bounded to 1 GiB per invocation, and reaching any limit MUST fail without uploading truncated audio.

#### Scenario: Large video produces acceptable audio

- **WHEN** a video exceeds the SDK's original-file upload-size limit but its prepared audio fits the upload and temporary-output limits
- **THEN** the CLI validates the upload limit against the prepared audio and does not reject the video solely for its original size

#### Scenario: Deadline or temporary-output limit is reached

- **WHEN** preparation exceeds the configured deadline, available space or output budget
- **THEN** the process is terminated, submission fails and temporary outputs are cleaned
- **AND** a size-limited or incomplete audio file is not treated as a successful conversion

#### Scenario: Invalid media timeout

- **WHEN** `--media-timeout` is zero, negative, non-integer or exceeds the representable positive-integer range
- **THEN** the command fails option validation before starting media tools or a submission request

### Requirement: Owned processes and temporary files

The CLI SHALL use private temporary storage and launch media tools with argument arrays without shell interpolation or inherited Echo credentials. Preparation processes and files MUST be cleaned on success, conversion failure, timeout and supported cancellation, including Windows. Input files and unrelated temporary files MUST never be removed.

#### Scenario: Source has spaces and shell-like characters

- **WHEN** a supported file path contains spaces, Unicode or shell metacharacters
- **THEN** that exact path is processed as one literal argument and no shell command from it is executed

#### Scenario: Submission succeeds or fails

- **WHEN** SDK submission finishes or throws after preparation
- **THEN** its input streams are closed and prepared temporary files are removed before polling or command exit

#### Scenario: User cancels local preparation

- **WHEN** the operation receives supported user cancellation while a media process is active
- **THEN** the child process stops, owned files are removed and the command exits non-zero without a successful result payload
- **AND** uncatchable process termination or power loss is not falsely claimed to support immediate cleanup

### Requirement: Existing output and connection contracts

Media preparation SHALL keep the existing non-wait `file_ids`, wait JSON, raw transcript and output-file contracts. Progress, stream selection and errors MUST go to stderr, leaving stdout free of media-tool output. Existing credential precedence, configuration paths and SDK transcription options MUST remain unchanged.

#### Scenario: JSON or raw-result automation

- **WHEN** video is submitted in JSON, raw-result or output-file mode
- **THEN** stdout and the requested output file contain only the results permitted by the existing mode
- **AND** probe output, preparation logs and binary paths do not contaminate them

### Requirement: Echo compatibility acceptance

Automatic preparation MUST NOT be released until the fallback format and every enabled copy route have recorded acceptance against Echo, including channel/rate preservation and timeline fixtures. Public OpenAPI and controlled local mocks alone MUST NOT be treated as proof of codec acceptance. Unverified routes MUST remain disabled.

#### Scenario: Copy route lacks service evidence

- **WHEN** a candidate container/codec works locally but has no recorded Echo acceptance
- **THEN** it is not enabled as a copy route and preparation uses a verified fallback or fails before upload

#### Scenario: Fallback acceptance is unavailable

- **WHEN** the required fallback or timeline acceptance cannot be completed
- **THEN** automatic preparation remains unreleasable and #60 is not reported complete
- **AND** the distribution-only toolkit work can be reviewed independently without being described as the completed user story

### Requirement: Documented preparation controls

Command help, the hand-written docs/cli.md guide linked from README, and the maintained transcription-skill CLI reference SHALL document automatic extraction, the three media options, stream-index semantics, resource limits, toolkit location and bypass behavior. Documentation MUST distinguish native distribution support from optional Composer media-tool configuration.

#### Scenario: Discover video preparation behavior

- **WHEN** a user follows the README link to the CLI guide, reads submit --help or the maintained command reference
- **THEN** they can understand the automatic path and use --audio-stream, --no-extract-audio and --media-timeout without changing the default one-command workflow

### Requirement: Reject unrepresentable fallback precision

Lossless fallback SHALL preserve integer source precision up to 24 bits with an explicitly validated output depth. It MUST reject source floating-point PCM and lossless integer inputs above 24 bits or with unknown effective depth when conversion is required.

#### Scenario: A 32-bit integer video track cannot use the verified fallback
- **WHEN** a video audio track contains PCM32 samples that need FLAC conversion
- **THEN** preparation fails with an unsupported-precision error before upload rather than silently quantizing the source

#### Scenario: A supported 24-bit integer track is converted
- **WHEN** a supported 24-bit integer track needs FLAC conversion
- **THEN** the output retains its sample rate, channels and 24-bit effective precision
