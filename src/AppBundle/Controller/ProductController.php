<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use AppBundle\Entity\Product;
use AppBundle\Entity\Result;


class ProductController extends Controller
{
    /**
     * 创建一个商品
     */
    public function createAction(Request $request)
    {
        $title = $request->get('title');
        $count = $request->get('count');

        $product = new Product();
        $product->setTitle($title);
        $product->setCount($count);

        $em = $this->getDoctrine()->getManager();
        $em->persist($product);

        $response = new Response();
        $response->headers->set('Content-Type', 'Application/json');

        if (!$em->flush()) {
            $response->setContent(json_encode(['code' => 1]));
        } else {
            $response->setContent(json_encode(['code' => 0]));
        }

        return $response;
    }

    /**
     * 秒杀处理Action，单用户并发锁
     */
    public function spikeAction(Request $request)
    {
        $id     = $request->get('id');
        $user  = $request->get('user');

        $response = new Response();
        $response->headers->set('Content-Type', 'Application/Json');

        /**
         * 并发锁,此处解决的时同一时刻并发的问题，但没有解决多次重复提交秒杀
         */
        
        $product_redis = $this->get('app.product.redis');
        if ($product_redis->isUserSpiking($user)) {
            $response->setContent(json_encode(['code' => -2, 'user' => $user, 'message' => '请勿重复秒杀']));
            return $response;
        }

        $check  = $this->isSpikeAble($id);

        /**
         * 如果不可以秒杀
         */
        if ($check['is_spike_able'] === false) {
            $response->setContent(json_encode(['code' => 0, 'user' => $user, 'message' => '秒杀已结束']));
            return $response;
        }

        $product = $check['product'];

        if (!$this->userSpikeAble($product, $user)) {
            $response->setContent(json_encode(['code' => -1, 'user' => $user,'message' => '您已经秒杀成功，请勿重复秒杀']));
            return $response;
        }


        $result = new Result;
        $result->setProduct($product);
        $result->setUser($user);

        $em = $this->getDoctrine()->getManager();
        $em->persist($result);
        $em->flush();

        $response->setContent(json_encode([
            'code'      => 1,
            'user'      => $user,
            'message'   => '恭喜，秒杀成功',
            'product'   => [
                'id'    => $product->getId(),
                'title' => $product->getTitle()]
            ]));

        /**
         * 删除锁
         */
        $product_redis->setUserSpiked($user);

        return $response;
    }

    public function isSpikeAble($id)
    {
        $rep    = $this->getDoctrine()->getManager()->getRepository('AppBundle\Entity\Product');

        $product = $rep->find($id);

        if ($product !== null) {
            return ['product' => $product, 'is_spike_able' => $product->isSpikeAble()];
        } else {
            return ['product' => null, 'is_spike_able' => false];
        }
    }

    /**
     * 检测某个用户是否还可以秒杀某个商品
     */
    public function userSpikeAble(Product $product, $user)
    {
        $results = $product->getResults();

        foreach ($results as $result) {
            if ($result->getUser() == $user) {
                return false;
            }
        }

        return true;
    }
}

