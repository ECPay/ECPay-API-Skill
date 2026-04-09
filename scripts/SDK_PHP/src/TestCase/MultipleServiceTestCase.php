<?php

namespace Ecpay\Sdk\TestCase;

use Faker\Generator;
use Ecpay\Sdk\Traits\StageInfo;
use PHPUnit\Framework\TestCase;
use Ecpay\Sdk\Factories\Factory;
use Faker\Factory as FakerFactory;
use Ecpay\Sdk\Services\CheckMacValueService;

class MultipleServiceTestCase extends TestCase
{
    use StageInfo;

    /**
     * Factory
     *
     * @var Factory
     */
    protected $factory;

    /**
     * fzaninotto/faker
     *
     * @var Generator
     */
    protected $faker;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->setFactory($this->getSha256CmvFactory());
        $this->faker = FakerFactory::create();
    }

    /**
     * 建立類別
     *
     * @param  string $class
     * @return mixed
     */
    protected function create($class)
    {
        return $this->factory->create($class);
    }

    /**
     * 取得 MD5 壓碼工廠
     *
     * @return Factory
     */
    protected function getMd5CmvFactory()
    {
        return new Factory([
            'hashIv' => $this->stageOtpHashIv,
            'hashKey' => $this->stageOtpHashKey,
            'hashMethod' => CheckMacValueService::METHOD_MD5,
        ]);
    }

    /**
     * 取得 SHA256 壓碼工廠
     *
     * @return Factory
     */
    protected function getSha256CmvFactory()
    {
        return new Factory([
            'hashIv' => $this->stageOtpHashIv,
            'hashKey' => $this->stageOtpHashKey,
            'hashMethod' => CheckMacValueService::METHOD_SHA256,
        ]);
    }

    /**
     * 設定工廠
     *
     * @param  Factory $factory
     * @return void
     */
    protected function setFactory(Factory $factory)
    {
        $this->factory = $factory;
    }
}
