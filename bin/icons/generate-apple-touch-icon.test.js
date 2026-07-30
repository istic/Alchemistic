import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import sharp from 'sharp';
import { afterEach, describe, expect, it } from 'vitest';
import { generateAppleTouchIcon } from './generate-apple-touch-icon.js';

const FIXTURE_GLYPH = path.join(import.meta.dirname, 'test-fixtures', 'glyph.svg');
const CONFIG = { glyph: FIXTURE_GLYPH, backgroundColor: '#6A2AAC' };

let outputDir;

afterEach(async () => {
    if (outputDir) {
        await fs.rm(outputDir, { recursive: true, force: true });
        outputDir = undefined;
    }
});

async function freshOutputDir() {
    outputDir = await fs.mkdtemp(path.join(os.tmpdir(), 'alchemistic-apple-icon-'));

    return outputDir;
}

// This app has no resources/branding/bloom.icon Apple Icon Composer bundle, so
// generateAppleTouchIcon always takes the flat-glyph fallback path here.
describe('generateAppleTouchIcon (flat fallback, no composer bundle)', () => {
    it('renders a 1024x1024 RGBA PNG', async () => {
        const dir = await freshOutputDir();

        await generateAppleTouchIcon(CONFIG, dir);

        const { width, height, channels } = await sharp(path.join(dir, 'apple-touch-icon.png')).metadata();

        expect({ width, height, channels }).toEqual({ width: 1024, height: 1024, channels: 4 });
    });

    it('draws the glyph on the background color, not a blank square', async () => {
        const dir = await freshOutputDir();

        await generateAppleTouchIcon(CONFIG, dir);

        const { data, info } = await sharp(path.join(dir, 'apple-touch-icon.png'))
            .raw()
            .toBuffer({ resolveWithObject: true });

        const pixelAt = (x, y) => {
            const offset = (y * info.width + x) * info.channels;

            return [data[offset], data[offset + 1], data[offset + 2]];
        };

        // Corner sits outside the centered glyph: pure background color.
        expect(pixelAt(0, 0)).toEqual([0x6a, 0x2a, 0xac]);
        // Center sits inside the glyph (a white circle in the fixture).
        expect(pixelAt(512, 512)).toEqual([0xff, 0xff, 0xff]);
    });
});
