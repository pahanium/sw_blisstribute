<?php

use Shopware\CustomModels\Blisstribute\BlisstributeOrderRepository;

/**
 * blisstribute order controller
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @method BlisstributeOrderRepository getRepository()
 */
class Shopware_Controllers_Backend_BlisstributeOrder extends Shopware_Controllers_Backend_Application
{
    /**
     * model class
     *
     * @var string
     */
    protected $model = 'Shopware\CustomModels\Blisstribute\BlisstributeOrder';

    /**
     * controller alias
     *
     * @var string
     */
    protected $alias = 'blisstribute_order';

    /**
     * plugin
     *
     * @var
     */
    private $plugin;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->plugin = $this->get('plugins')->Backend()->ExitBBlisstribute();
        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (version_compare(Shopware::VERSION, '4.2.0', '<') && Shopware::VERSION != '___VERSION___') {
            $name = ucfirst($name);

            /** @noinspection PhpUndefinedMethodInspection */
            return Shopware()->Bootstrap()->getResource($name);
        }
        return Shopware()->Container()->get($name);
    }

    /**
     * @inheritdoc
     */
    protected function getListQuery()
    {
        $builder = parent::getListQuery();

        $builder->innerJoin('blisstribute_order.order', 'o');
        $builder->addSelect(array('o'));
        $builder->addOrderBy('o.id', 'DESC');

        // searching
        $filters = $this->Request()->getParam('filter');

        if (!is_null($filters)) {
            foreach ($filters as $filter) {
                if ($filter['property'] == 'search') {
                    $value = $filter['value'];

                    $search = '%' . $value . '%';

                    if (!is_null($value)) {
                        $builder->andWhere('o.number LIKE :search');

                        $builder->setParameter('search', $search);
                    }
                }
            }
        }

        return $builder;
    }

    /**
     * starts syncing of selected articles
     *
     * @return void
     */
    public function syncAction()
    {
        try {
            $blisstributeOrderId = $this->Request()->getParam('id');
            $blisstributeOrder = $this->getRepository()->find($blisstributeOrderId);
            if ($blisstributeOrder === null) {
                $this->View()->assign(array(
                    'success' => false,
                    'error' => 'unknown blisstribute order'
                ));

                return;
            }

            require_once __DIR__ . '/../../Components/Blisstribute/Order/Sync.php';

            /** @noinspection PhpUndefinedMethodInspection */
            $orderSync = new Shopware_Components_Blisstribute_Order_Sync($this->plugin->Config());
            $result = $orderSync->processSingleOrderSync($blisstributeOrder);

            $this->View()->assign(array(
                'success' => $result,
                'error' => (($result == true) ? '' : $orderSync->getLastError()),
            ));
        } catch (Exception $ex) {
            $this->View()->assign(array(
                'success' => false,
                'error' => $ex->getMessage(),
            ));
        }
    }

    /**
     * resets the order sync locks
     *
     * @return void
     */
    public function resetLockAction()
    {
        $sql = 'DELETE FROM s_plugin_blisstribute_task_lock WHERE task_name LIKE :taskName';
        Shopware()->Db()->query($sql, array('taskName' => '%order_sync%'));

        $this->View()->assign(array(
            'success' => true,
            'message' => 'foobar'
        ));
    }

    /**
     * resets the order sync locks
     *
     * @return void
     */
    public function resetOrderSyncAction()
    {
        $blisstributeOrderId = $this->Request()->getParam('id');
        $sql = 'UPDATE s_plugin_blisstribute_orders set transfer_status = 1 WHERE s_order_id = :orderId';
        Shopware()->Db()->query($sql, array('orderId' => $blisstributeOrderId));

        $this->View()->assign(array(
            'success' => true,
            'order_id' => $blisstributeOrderId
        ));
    }

    /**
     * Check for invalid order transfers and return warning
     */
    public function getInvalidOrderTransfersAction()
    {
        $sqlSelectInvalidOrderTransfers = "SELECT s_order.ordernumber AS ordernumber FROM s_plugin_blisstribute_orders 
                                           LEFT JOIN s_order ON s_order.id = s_order_id
                                           WHERE transfer_status = 10 OR transfer_status = 11 
                                           OR transfer_status = 20 OR transfer_status = 21";

        $invalidTransfers = Shopware()->Db()->fetchAll($sqlSelectInvalidOrderTransfers);

        $this->View()->assign(array(
            'success' => true,
            'data' => $invalidTransfers,
            'count' => count($invalidTransfers)
        ));
    }

    public function getOrderByNumberAction()
    {
        $orderNumber = $this->Request()->getParam('orderNumber');

        $order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')->findOneBy(array(
            'number' => $orderNumber
        ));

        $this->View()->assign(array(
            'success' => true,
            'data' => $order->getId()
        ));
    }
}