<?php declare(strict_types = 1);

namespace Poly1305;

class Poly1305Test extends \PHPUnit_Framework_TestCase
{
    function implementationProvider()
    {
        $impl = [[new Native]];

        if (extension_loaded('gmp')) {
            $impl[] = [new GMP];
        }

        return $impl;
    }

    /**
     * @dataProvider implementationProvider
     * @expectedException \InvalidArgumentException
     */
    function testInvalidKey(Streamable $poly1305)
    {
        $poly1305->init('123');
    }

    /**
     * @dataProvider implementationProvider
     * @expectedException \TypeError
     */
    function testInvalidMessage(Streamable $poly1305)
    {
        $ctx = $poly1305->init('01234567890123456789012345678901');
        $poly1305->update($ctx, null);
    }

    /**
     * @dataProvider implementationProvider
     */
    function testNaCL(Streamable $poly1305)
    {
        /* example from nacl */
        $key = pack('C*',
            0xee,0xa6,0xa7,0x25,0x1c,0x1e,0x72,0x91,
            0x6d,0x11,0xc2,0xcb,0x21,0x4d,0x3c,0x25,
            0x25,0x39,0x12,0x1d,0x8e,0x23,0x4e,0x65,
            0x2d,0x65,0x1f,0xa4,0xc8,0xcf,0xf8,0x80
        );

        $message = pack('C*',
            0x8e,0x99,0x3b,0x9f,0x48,0x68,0x12,0x73,
            0xc2,0x96,0x50,0xba,0x32,0xfc,0x76,0xce,
            0x48,0x33,0x2e,0xa7,0x16,0x4d,0x96,0xa4,
            0x47,0x6f,0xb8,0xc5,0x31,0xa1,0x18,0x6a,
            0xc0,0xdf,0xc1,0x7c,0x98,0xdc,0xe8,0x7b,
            0x4d,0xa7,0xf0,0x11,0xec,0x48,0xc9,0x72,
            0x71,0xd2,0xc2,0x0f,0x9b,0x92,0x8f,0xe2,
            0x27,0x0d,0x6f,0xb8,0x63,0xd5,0x17,0x38,
            0xb4,0x8e,0xee,0xe3,0x14,0xa7,0xcc,0x8a,
            0xb9,0x32,0x16,0x45,0x48,0xe5,0x26,0xae,
            0x90,0x22,0x43,0x68,0x51,0x7a,0xcf,0xea,
            0xbd,0x6b,0xb3,0x73,0x2b,0xc0,0xe9,0xda,
            0x99,0x83,0x2b,0x61,0xca,0x01,0xb6,0xde,
            0x56,0x24,0x4a,0x9e,0x88,0xd5,0xf9,0xb3,
            0x79,0x73,0xf6,0x22,0xa4,0x3d,0x14,0xa6,
            0x59,0x9b,0x1f,0x65,0x4c,0xb4,0x5a,0x74,
            0xe3,0x55,0xa5
        );

        $mac = pack('C*',
            0xf3,0xff,0xc7,0x70,0x3f,0x94,0x00,0xe5,
            0x2a,0x7d,0xfb,0x4b,0x3d,0x33,0x05,0xd9
        );

        $ctx = $poly1305->init($key);
        $poly1305->update($ctx, $message);

        $this->assertTrue($mac === $poly1305->finish($ctx));
    }

    /**
     * @dataProvider implementationProvider
     */
    function testWrap(Streamable $poly1305)
    {
        /* generates a final value of (2^130 - 2) == 3 */
        $key = pack('C*',
            0x02,0x00,0x00,0x00,0x00,0x00,0x00,0x00,
            0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,
            0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,
            0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00
        );

        $message = pack('C*',
            0xff,0xff,0xff,0xff,0xff,0xff,0xff,0xff,
            0xff,0xff,0xff,0xff,0xff,0xff,0xff,0xff
        );

        $mac = pack('C*',
            0x03,0x00,0x00,0x00,0x00,0x00,0x00,0x00,
            0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00
        );

        $ctx = $poly1305->init($key);
        $poly1305->update($ctx, $message);

        $this->assertTrue($mac === $poly1305->finish($ctx));
    }

    /**
     * @dataProvider implementationProvider
     */
    function testTotal(Streamable $poly1305)
    {
        /*
            mac of the macs of messages of length 0 to 256, where the key and messages
            have all their values set to the length
        */
        $key = pack('C*',
            0x01,0x02,0x03,0x04,0x05,0x06,0x07,
            0xff,0xfe,0xfd,0xfc,0xfb,0xfa,0xf9,
            0xff,0xff,0xff,0xff,0xff,0xff,0xff,
            0xff,0xff,0xff,0xff,0xff,0xff,0xff,
            0x00,0x00,0x00,0x00
        );

        $mac = pack('C*',
            0x64,0xaf,0xe2,0xe8,0xd6,0xad,0x7b,0xbd,
            0xd2,0x87,0xf9,0x7c,0x44,0x62,0x3d,0x39
        );

        $ctxTotal = $poly1305->init($key);
        for ($i = 0; $i < 256; $i++) {
            $ctx = $poly1305->init(str_repeat(chr($i), 32));
            $poly1305->update($ctx, str_repeat(chr($i), $i));
            $poly1305->update($ctxTotal, $poly1305->finish($ctx));
        }

        $this->assertTrue($mac === $poly1305->finish($ctxTotal));
    }
}
