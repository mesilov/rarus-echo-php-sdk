"""Measure raw/gzip Docker save layer streams without loading images into memory."""
import gzip
import io
import json
import tarfile
import zlib


class MeasuredReader:
    def __init__(self, stream, compress):
        self.stream = stream
        self.uncompressed = 0
        self.compressed = 0
        self.compressor = zlib.compressobj(9, zlib.DEFLATED, 31) if compress else None

    def read(self, size=-1):
        data = self.stream.read(size)
        self.uncompressed += len(data)
        if self.compressor:
            self.compressed += len(self.compressor.compress(data))
        return data

    def finish(self):
        while self.read(1024 * 1024):
            pass
        if self.compressor:
            self.compressed += len(self.compressor.flush())


def saved_image_metrics(path):
    result = {'uncompressed_layer_tar_bytes': 0, 'layer_gzip_bytes': 0,
              'media_layer_gzip_bytes': 0, 'media_layers': 0}
    with tarfile.open(path) as saved:
        manifest = json.load(saved.extractfile('manifest.json'))
        if len(manifest) != 1:
            raise ValueError('Size acceptance requires exactly one saved image')
        for layer in manifest[0]['Layers']:
            member = saved.getmember(layer)
            with saved.extractfile(member) as stream:
                buffered = io.BufferedReader(stream)
                is_gzip = buffered.peek(2)[:2] == b'\x1f\x8b'
                decoded = gzip.GzipFile(fileobj=buffered) if is_gzip else buffered
                reader = MeasuredReader(decoded, compress=not is_gzip)
                has_media = False
                with tarfile.open(fileobj=reader, mode='r|') as contents:
                    for entry in contents:
                        if entry.name.lstrip('./').startswith('opt/rarus-echo/media/'):
                            has_media = True
                reader.finish()
                compressed = member.size if is_gzip else reader.compressed
                result['uncompressed_layer_tar_bytes'] += reader.uncompressed
                result['layer_gzip_bytes'] += compressed
                if has_media:
                    result['media_layers'] += 1
                    result['media_layer_gzip_bytes'] += compressed
    return result
