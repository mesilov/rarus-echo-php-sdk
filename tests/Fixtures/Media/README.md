# Media acceptance fixtures

These are original project-generated sine tones and geometric frames, dedicated
to the public domain under [CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/).
They contain no recordings, speech or personal data. `generate.py` is the source;
`manifest.json` records the committed bytes, stream properties and ordered encoded
audio-packet hashes. Fixture generation requires a full development FFmpeg; the
compact distributed toolkit only consumes the committed fixtures.

```sh
python3 tests/Fixtures/Media/generate.py --ffmpeg /path/to/full/ffmpeg --ffprobe /path/to/full/ffprobe
```

`stereo.wav` is two seconds of 48 kHz stereo PCM16, starting with 0.5 seconds of
silence; the left and right tones are 440 and 880 Hz. Video fixtures use a tiny
black frame. `delayed.mp4` adds a two-second audio offset. `two-tracks.mkv` marks
the second audio track default. Audio-only containers and attached artwork test
classification; `no-audio.mp4` and `corrupt.mp4` test failures.

Live Echo acceptance uses separately generated, non-sensitive speech in ignored
runtime storage. These tone fixtures and local mock uploads do not prove service
codec acceptance or recognition timestamp accuracy.
