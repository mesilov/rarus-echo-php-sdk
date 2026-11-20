## MODIFIED Requirements

### Requirement: Verified output format policy

Preparation SHALL copy encoded audio when its codec/container and timeline are accepted by the committed Echo compatibility matrix. Otherwise it SHALL transcode a supported audio codec to the matrix's verified lossless fallback. It MUST preserve channel count, sample rate and supported integer source precision, and MUST reject source float PCM or lossless precision that the verified fallback cannot represent before upload. For a lossy source codec the fallback SHALL be encoded with 16-bit samples.

#### Scenario: Compatible stream copy

- **WHEN** the selected stream has a verified copy destination and needs no timeline normalization
- **THEN** the output contains only that audio stream with identical encoded audio packets

#### Scenario: Audio conversion is required

- **WHEN** copy is unsuitable but the input decoder and lossless fallback are verified
- **THEN** preparation converts the audio without introducing lossy compression, resampling or channel mixing

#### Scenario: Lossy source needs conversion

- **WHEN** a lossy-codec track such as AAC needs the lossless fallback
- **THEN** the output is 16-bit FLAC with the source sample rate and channel count

#### Scenario: No verified conversion route

- **WHEN** the stream's codec or required output route is outside the accepted matrix
- **THEN** the command explains the unsupported route and sends neither a partial output nor the original video

### Requirement: Source timeline preservation

Prepared audio SHALL represent the selected stream on the original container's playback timeline, including leading silence where required. Discontinuities in the decoded source timeline SHALL be repaired rather than rejected: gaps MUST become decoded silence of the same length and overlaps MUST be trimmed, so that speech keeps its source position. A discontinuous source MUST NOT use the stream-copy route. The prepared output MUST be continuous and its decoded-signal alignment tolerance MUST be at most 50 ms. Preparation MUST NOT shift or truncate speech silently: every repair MUST be reported on stderr, and preparation MUST fail if the prepared output does not meet the tolerance.

#### Scenario: Audio begins later than video playback

- **WHEN** the selected audio starts after the container's playback origin
- **THEN** preparation preserves that gap as decoded silence in a verified output route instead of rebasing speech to zero

#### Scenario: Internal gap in the decoded timeline

- **WHEN** the selected track is missing a fragment, such as a 100 ms timestamp gap in the middle of a recording
- **THEN** preparation succeeds, fills the gap with silence and keeps speech after the gap at its source position within 50 ms
- **AND** stderr reports the number of repaired discontinuities

#### Scenario: Overlapping or backward timestamps

- **WHEN** frame timestamps overlap or move backwards
- **THEN** preparation trims the overlap and continues instead of failing

#### Scenario: Timeline cannot be represented safely

- **WHEN** no decoded frame provides usable timing, or the prepared output is discontinuous or deviates from the expected timeline by more than 50 ms
- **THEN** the CLI reports the timing limitation before upload
- **AND** this tolerance is not presented as a guarantee of Echo's recognition timestamp accuracy

### Requirement: Bounded preparation resources

`--media-timeout=<seconds>` SHALL set a positive per-input deadline for probe and preparation, defaulting to 600 seconds, independently of polling and HTTP timeouts. Prepared files MUST satisfy the SDK upload-size limit of 1 GiB. Total temporary output MUST be bounded to 2 GiB per invocation, and reaching any limit MUST fail without uploading truncated audio.

#### Scenario: Large video produces acceptable audio

- **WHEN** a video exceeds the SDK's original-file upload-size limit but its prepared audio fits the upload and temporary-output limits
- **THEN** the CLI validates the upload limit against the prepared audio and does not reject the video solely for its original size

#### Scenario: Prepared audio between 500 MiB and 1 GiB

- **WHEN** prepared audio is larger than 500 MiB but not larger than 1 GiB
- **THEN** it passes local size validation and is submitted

#### Scenario: Deadline or temporary-output limit is reached

- **WHEN** preparation exceeds the configured deadline, available space or output budget
- **THEN** the process is terminated, submission fails and temporary outputs are cleaned
- **AND** a size-limited or incomplete audio file is not treated as a successful conversion

#### Scenario: Invalid media timeout

- **WHEN** `--media-timeout` is zero, negative, non-integer or exceeds the representable positive-integer range
- **THEN** the command fails option validation before starting media tools or a submission request

## ADDED Requirements

### Requirement: Damaged recording tolerance

Preparation SHALL tolerate recordings partially damaged by recording software. Frames without a timestamp, unparsable probe entries and undecodable packets MUST NOT abort preparation while audio can still be decoded; lost regions MUST be represented as silence on the source timeline and reported on stderr. Preparation MUST fail only when no audio can be recovered or a resource limit is reached.

#### Scenario: Corrupt packets in the middle of a recording

- **WHEN** some audio packets of the selected track cannot be decoded
- **THEN** preparation completes, the remaining speech keeps its source position within 50 ms and stderr reports the damage

#### Scenario: Frame without timing

- **WHEN** a decoded frame has no usable timestamp
- **THEN** it is treated as continuing the previous frame and preparation continues

#### Scenario: Nothing is recoverable

- **WHEN** no audio frame of the selected track can be decoded
- **THEN** the command fails with a concise diagnostic before any upload
