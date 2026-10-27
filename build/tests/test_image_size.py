"""Docker saved-layer gzip and uncompressed measurements use byte streams."""
import gzip
import io
import json
from pathlib import Path
import sys
import tarfile
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))


class ImageSizeTests(unittest.TestCase):
    def test_raw_and_gzip_saved_layers_have_same_uncompressed_byte_count(self):
        self.assertTrue((Path(__file__).resolve().parents[1] / 'media/image_size.py').is_file(),
                        'saved image sizes must be decoded, not inferred from engine Size')
        from media.image_size import saved_image_metrics
        layer = io.BytesIO()
        with tarfile.open(fileobj=layer, mode='w') as archive:
            member = tarfile.TarInfo('opt/rarus-echo/media/libexec/ffmpeg')
            member.size = 10000
            archive.addfile(member, io.BytesIO(b'a' * member.size))
        raw = layer.getvalue()
        for data in [raw, gzip.compress(raw, mtime=0)]:
            with self.subTest(gzip=data[:2] == b'\x1f\x8b'), tempfile.TemporaryDirectory() as temp:
                path = Path(temp) / 'image.tar'
                manifest = json.dumps([{'Layers': ['layer.tar']}]).encode()
                with tarfile.open(path, 'w') as archive:
                    for name, payload in [('manifest.json', manifest), ('layer.tar', data)]:
                        member = tarfile.TarInfo(name)
                        member.size = len(payload)
                        archive.addfile(member, io.BytesIO(payload))
                result = saved_image_metrics(path)
                self.assertEqual(len(raw), result['uncompressed_layer_tar_bytes'])
                self.assertEqual(1, result['media_layers'])
                self.assertEqual(result['layer_gzip_bytes'], result['media_layer_gzip_bytes'])
                self.assertLess(result['layer_gzip_bytes'], len(raw))
                if data is not raw:
                    self.assertEqual(len(data), result['layer_gzip_bytes'])


if __name__ == '__main__':
    unittest.main()
